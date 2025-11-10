<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    private static string $tmpDir;
    private static string $dbFile;
    private static $serverProc = null;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'login_test_' . bin2hex(random_bytes(4));
        if (!mkdir(self::$tmpDir) && !is_dir(self::$tmpDir)) {
            throw new \RuntimeException('No se pudo crear tmpDir');
        }

        $projectRoot = realpath(__DIR__ . '/../');
        $loginSrc = $projectRoot . '/apipps/login.php';   // ← ARREGLADO
        if (!is_file($loginSrc)) {
            throw new \RuntimeException("No se encontró login.php en $loginSrc");
        }
        copy($loginSrc, self::$tmpDir . '/login.php');

        self::$dbFile = self::$tmpDir . '/testing.sqlite';

        $configPhp = <<<PHP
        <?php
        declare(strict_types=1);
        \$pdo = new PDO('sqlite:' . __DIR__ . '/testing.sqlite');
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          email TEXT NOT NULL UNIQUE,
          password_hash TEXT NOT NULL,
          name TEXT,
          fecha_nacimiento TEXT,
          api_token TEXT,
          last_login TEXT
        );
        SQL);
        \$email = 'test@acme.com';
        \$exists = \$pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        \$exists->execute([\$email]);
        if ((int)\$exists->fetchColumn() === 0) {
            \$stmt = \$pdo->prepare('INSERT INTO users (email, password_hash, name, fecha_nacimiento) VALUES (?,?,?,?)');
            \$stmt->execute([\$email, password_hash('secret123', PASSWORD_DEFAULT), 'Tester', '1990-01-01']);
        }
        PHP;
        file_put_contents(self::$tmpDir . '/config.php', $configPhp);

        self::$port = random_int(8000, 8999);
        $cmd = sprintf('php -S 127.0.0.1:%d -t %s', self::$port, escapeshellarg(self::$tmpDir));
        self::$serverProc = proc_open($cmd, [
            0 => ['pipe','r'],
            1 => ['file', self::$tmpDir.'/server.out', 'w'],
            2 => ['file', self::$tmpDir.'/server.err', 'w'],
        ], $pipes, self::$tmpDir);

        if (!\is_resource(self::$serverProc)) {
            throw new \RuntimeException('No se pudo iniciar el servidor PHP embebido');
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
        @unlink(self::$tmpDir . '/login.php');
        @unlink(self::$tmpDir . '/server.out');
        @unlink(self::$tmpDir . '/server.err');
        @rmdir(self::$tmpDir);
    }

    private function postJson(array|string $payload, int &$code = null): array
    {
        $url = sprintf('http://127.0.0.1:%d/login.php', self::$port);
        $json = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload;

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $json,
                'ignore_errors' => true,
            ]
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        $meta = $http_response_header ?? [];
        $code = 0;
        foreach ($meta as $h) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; break; }
        }
        $decoded = json_decode($resp ?: 'null', true);
        return is_array($decoded) ? $decoded : ['_raw' => $resp];
    }

    public function testJsonInvalido(): void
    {
        $code = 0;
        $resp = $this->postJson('{invalid json', $code);
        $this->assertSame(400, $code);
        $this->assertSame('JSON inválido', $resp['error'] ?? null);
    }

    public function testCamposRequeridos(): void
    {
        $code = 0;
        $resp = $this->postJson(['email' => '', 'password' => ''], $code);
        $this->assertSame(400, $code);
        $this->assertSame('Email y contraseña requeridos', $resp['error'] ?? null);
    }

    public function testUsuarioInexistente(): void
    {
        $code = 0;
        $resp = $this->postJson(['email' => 'no@nadie.com', 'password' => 'x'], $code);
        $this->assertSame(401, $code);
        $this->assertSame('Credenciales inválidas', $resp['error'] ?? null);
    }

    public function testPasswordIncorrecto(): void
    {
        $code = 0;
        $resp = $this->postJson(['email' => 'test@acme.com', 'password' => 'mal'], $code);
        $this->assertSame(401, $code);
        $this->assertSame('Credenciales inválidas', $resp['error'] ?? null);
    }

    public function testLoginOk(): void
    {
        $code = 0;
        $resp = $this->postJson(['email' => 'test@acme.com', 'password' => 'secret123'], $code);

        $this->assertSame(200, $code);
        $this->assertTrue($resp['ok'] ?? false);

        $this->assertArrayHasKey('token', $resp);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $resp['token']); // bin2hex(24)

        $u = $resp['user'] ?? null;
        $this->assertIsArray($u);
        $this->assertSame('test@acme.com', $u['email'] ?? null);
        $this->assertSame('Tester', $u['name'] ?? null);
        $this->assertSame('1990-01-01', $u['fecha_nacimiento'] ?? null);
        $this->assertIsInt($u['id'] ?? null);
    }
}
