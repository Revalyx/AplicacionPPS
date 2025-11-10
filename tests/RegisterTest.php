<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegisterTest extends TestCase
{
    private static string $tmpDir;
    private static string $dbFile;
    private static $serverProc = null;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        // Carpeta temporal y copia del endpoint
        self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'register_test_' . bin2hex(random_bytes(4));
        if (!mkdir(self::$tmpDir) && !is_dir(self::$tmpDir)) {
            throw new \RuntimeException('No se pudo crear tmpDir');
        }

        $projectRoot = realpath(__DIR__ . '/../');
        $registerSrc = $projectRoot . '/apipps/register.php';
        if (!is_file($registerSrc)) {
            throw new \RuntimeException("No se encontró register.php en $registerSrc");
        }
        copy($registerSrc, self::$tmpDir . '/register.php');

        // BD + config de pruebas (SQLite)
        self::$dbFile = self::$tmpDir . '/testing.sqlite';

        // OJO: el cierre PHP; va a columna 0 (sin espacios).
        $configPhp = <<<PHP
<?php
declare(strict_types=1);

/**
 * Wrapper de PDO que intercepta la consulta a INFORMATION_SCHEMA.COLUMNS
 * y la emula con PRAGMA table_info(users) en SQLite.
 * NO extiende PDO para evitar "PDO object not initialized".
 */
class PdoProxy {
    private PDO \$inner;

    public function __construct(PDO \$pdo){ \$this->inner = \$pdo; }

    public function prepare(\$query, \$options = []){
        \$q = trim(\$query);

        if (stripos(\$q, 'INFORMATION_SCHEMA.COLUMNS') !== false
            && stripos(\$q, "TABLE_NAME = 'users'") !== false) {

            \$columnsStmt = \$this->inner->query("PRAGMA table_info(users)");
            \$cols = [];
            foreach (\$columnsStmt->fetchAll(PDO::FETCH_ASSOC) as \$row) {
                \$cols[] = \$row['name'];
            }

            // Statement mínimo compatible con execute()->fetchAll(PDO::FETCH_COLUMN)
            return new class(\$cols) {
                private array \$cols;
                public function __construct(array \$c){ \$this->cols = \$c; }
                public function execute(\$params = null){ return true; }
                public function fetchAll(\$mode = null){ return \$this->cols; }
            };
        }

        return \$this->inner->prepare(\$query, \$options);
    }

    public function lastInsertId(?string \$name = null): string|false {
        return \$this->inner->lastInsertId(\$name);
    }

    public function __call(\$name, \$args){
        return \$this->inner->\$name(...\$args);
    }
}

// Crear PDO SQLite real
\$real = new PDO('sqlite:' . __DIR__ . '/testing.sqlite');
\$real->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Simular NOW()
\$real->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));

// Esquema users con todas las columnas que tu endpoint puede usar
\$real->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT,
  -- NO creamos email_lower para forzar LOWER(email)
  password_hash TEXT,
  password TEXT,
  name TEXT,
  full_name TEXT,
  fecha_nacimiento TEXT,
  api_token TEXT,
  created_at TEXT,
  updated_at TEXT,
  deleted_at TEXT
);
SQL);

// Exponer \$pdo como proxy
\$pdo = new PdoProxy(\$real);
PHP;

        file_put_contents(self::$tmpDir . '/config.php', $configPhp);

        // Servidor PHP embebido
        self::$port = random_int(8200, 8999);
        $cmd = sprintf('php -S 127.0.0.1:%d -t %s', self::$port, escapeshellarg(self::$tmpDir));
        self::$serverProc = proc_open($cmd, [
            0 => ['pipe','r'],
            1 => ['file', self::$tmpDir.'/server.out', 'w'],
            2 => ['file', self::$tmpDir.'/server.err', 'w'],
        ], $pipes, self::$tmpDir);
        if (!\is_resource(self::$serverProc)) {
            throw new \RuntimeException('No se pudo iniciar el servidor embebido');
        }
        usleep(300_000);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProc)) {
            proc_terminate(self::$serverProc);
            proc_close(self::$serverProc);
        }
        @unlink(self::$tmpDir . '/testing.sqlite');
        @unlink(self::$tmpDir . '/config.php');
        @unlink(self::$tmpDir . '/register.php');
        @unlink(self::$tmpDir . '/server.out');
        @unlink(self::$tmpDir . '/server.err');
        @rmdir(self::$tmpDir);
    }

    private function url(): string
    {
        // debug=1 para que el endpoint muestre el error real si peta
        return sprintf('http://127.0.0.1:%d/register.php?debug=1', self::$port);
    }

    private function post(array|string $payload, int &$code = null): array
    {
        $json = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content'=> $json,
                'ignore_errors' => true,
            ]
        ]);
        $body = file_get_contents($this->url(), false, $ctx);
        $code = 0;
        foreach (($http_response_header ?? []) as $h){
            if (preg_match('#HTTP/\S+\s+(\d{3})#',$h,$m)){ $code=(int)$m[1]; break; }
        }
        $d = json_decode($body ?: 'null', true);
        return is_array($d) ? $d : ['_raw'=>$body];
    }

    // ---------- TESTS ----------

    public function testJsonInvalido(): void
    {
        $c = 0; $r = $this->post('{no es json', $c);
        $this->assertSame(400, $c);
        $this->assertSame('JSON inválido', $r['error'] ?? null);
    }

    public function testCamposObligatorios(): void
    {
        $c = 0; $r = $this->post(['email'=>'', 'password'=>''], $c);
        $this->assertSame(400, $c);
        $this->assertSame('Email y password son obligatorios', $r['error'] ?? null);
    }

    public function testEmailInvalidoYPasswordCorta(): void
    {
        $c = 0; $r = $this->post(['email'=>'no-es-email','password'=>'123'], $c);
        $this->assertSame(422, $c);
        $this->assertSame('Email no válido', $r['error'] ?? null);

        $c = 0; $r = $this->post(['email'=>'a@b.com','password'=>'12345'], $c);
        $this->assertSame(422, $c);
        $this->assertSame('La contraseña debe tener al menos 6 caracteres', $r['error'] ?? null);
    }

    public function testRegistroOkConNombreYFecha(): void
    {
        $c = 0;
        $r = $this->post([
            'email' => 'User@Acme.com',   // probar normalización (LOWER en búsqueda)
            'password' => 'secret123',
            'name' => 'Pepe',
            'fecha_nacimiento' => '1999-12-31'
        ], $c);

        $this->assertSame(201, $c);
        $this->assertTrue($r['ok'] ?? false);
        $this->assertIsArray($r['user'] ?? null);

        $u = $r['user'];
        $this->assertSame('User@Acme.com', $u['email']);
        $this->assertSame('Pepe', $u['name']);
        $this->assertSame('1999-12-31', $u['fecha_nacimiento']);

        // si existe api_token se devuelve (nuestra tabla lo tiene)
        $this->assertArrayHasKey('token', $r);
        if (isset($r['token'])) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $r['token']);
        }
    }

    public function testEmailDuplicadoCaseInsensitive(): void
    {
        // 1º registro
        $c = 0; $this->post(['email'=>'dup@acme.com','password'=>'secret123'], $c);
        $this->assertSame(201, $c);

        // 2º con casing distinto
        $c = 0; $r = $this->post(['email'=>'DuP@AcMe.com','password'=>'secret123'], $c);
        $this->assertSame(409, $c);
        $this->assertSame('El email ya está registrado', $r['error'] ?? null);
    }

    public function testInsertaPasswordHashNoPlano(): void
    {
        // Registro
        $c = 0; $this->post(['email'=>'hash@acme.com','password'=>'secret123'], $c);
        $this->assertSame(201, $c);

        // Verificar en BD (que se usó password_hash, no password plano)
        $pdo = new PDO('sqlite:' . self::$dbFile);
        $row = $pdo->query("SELECT password_hash, password FROM users WHERE email='hash@acme.com'")
                   ->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row['password_hash'] ?? null);
        if (!empty($row['password'])) {
            $this->assertNotSame('secret123', $row['password']);
        }
    }
}
