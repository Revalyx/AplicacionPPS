<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UsersTest extends TestCase
{
    private static string $tmpDir;
    private static string $dbFile;
    private static $serverProc = null;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        // 1) Carpeta temporal y copia del endpoint
        self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'users_test_' . bin2hex(random_bytes(4));
        if (!mkdir(self::$tmpDir) && !is_dir(self::$tmpDir)) {
            throw new \RuntimeException('No se pudo crear tmpDir');
        }

        $projectRoot = realpath(__DIR__ . '/../');
        $usersSrc = $projectRoot . '/apipps/users.php';
        if (!is_file($usersSrc)) {
            throw new \RuntimeException("No se encontró users.php en $usersSrc");
        }
        copy($usersSrc, self::$tmpDir . '/users.php');

        // 2) BD + config de pruebas (SQLite)
        self::$dbFile = self::$tmpDir . '/testing.sqlite';

        // OJO: el cierre PHP; va a columna 0, sin espacios.
        $configPhp = <<<PHP
<?php
declare(strict_types=1);

// Crear PDO SQLite real
\$pdo = new PDO('sqlite:' . __DIR__ . '/testing.sqlite');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Simular NOW()
\$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));

// Esquema tabla users con las columnas que usa users.php
\$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  email_lower TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  name TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
SQL);
PHP;

        file_put_contents(self::$tmpDir . '/config.php', $configPhp);

        // 3) Servidor PHP embebido
        self::$port = random_int(8300, 8999);
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
        @unlink(self::$tmpDir . '/users.php');
        @unlink(self::$tmpDir . '/server.out');
        @unlink(self::$tmpDir . '/server.err');
        @rmdir(self::$tmpDir);
    }

    private function url(): string
    {
        return sprintf('http://127.0.0.1:%d/users.php', self::$port);
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

    // --------- TESTS ---------

    public function testJsonInvalido(): void
    {
        $c=0; $r=$this->post('{no es json', $c);
        $this->assertSame(400,$c);
        $this->assertSame('JSON inválido', $r['error'] ?? null);
    }

    public function testCamposObligatorios(): void
    {
        $c=0; $r=$this->post(['email'=>'','password'=>''], $c);
        $this->assertSame(400,$c);
        $this->assertSame('Email y contraseña requeridos', $r['error'] ?? null);
    }

    public function testEmailInvalido(): void
    {
        $c=0; $r=$this->post(['email'=>'no-es-email','password'=>'secreta'], $c);
        $this->assertSame(400,$c);
        $this->assertSame('Email inválido', $r['error'] ?? null);
    }

    public function testPasswordCorta(): void
    {
        $c=0; $r=$this->post(['email'=>'a@b.com','password'=>'123'], $c);
        $this->assertSame(400,$c);
        $this->assertSame('La contraseña debe tener al menos 6 caracteres', $r['error'] ?? null);
    }

    public function testRegistroOk(): void
    {
        $c=0; 
        $r=$this->post(['email'=>'User@Acme.com','password'=>'secret123','name'=>'Pepe'], $c);

        $this->assertSame(201,$c);
        $this->assertTrue($r['ok'] ?? false);
        $this->assertIsInt($r['id'] ?? null);

        // Verificar inserción real
        $pdo = new PDO('sqlite:' . self::$dbFile);
        $row = $pdo->query("SELECT email, email_lower, password_hash, name, created_at FROM users WHERE id = ".(int)$r['id'])
                   ->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('User@Acme.com', $row['email']);
        $this->assertSame('user@acme.com', $row['email_lower']); // normalizado
        $this->assertSame('Pepe', $row['name']);
        $this->assertNotEmpty($row['created_at']);

        // Debe ser un hash (no igual a la password en texto plano)
        $this->assertNotSame('secret123', $row['password_hash']);
        $this->assertTrue(password_verify('secret123', $row['password_hash']));
    }

    public function testEmailDuplicadoCaseInsensitive(): void
    {
        // 1º inserto
        $c=0; $this->post(['email'=>'dup@acme.com','password'=>'secret123'], $c);
        $this->assertSame(201,$c);

        // 2º con mayúsculas → debe chocar por email_lower
        $c=0; $r=$this->post(['email'=>'DuP@AcMe.com','password'=>'secret123'], $c);
        $this->assertSame(409,$c);
        $this->assertSame('El email ya está registrado', $r['error'] ?? null);
    }
}
