<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MedidasTest extends TestCase
{
    private static string $tmpDir;
    private static string $dbFile;
    private static $serverProc = null;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        // 1) Carpeta temporal para servir medidas.php + config.php
        self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medidas_test_' . bin2hex(random_bytes(4));
        if (!mkdir(self::$tmpDir) && !is_dir(self::$tmpDir)) {
            throw new \RuntimeException('No se pudo crear tmpDir');
        }

        // 2) Copiamos el endpoint original
        $projectRoot = realpath(__DIR__ . '/../');
        $medidasSrc  = $projectRoot . '/apipps/medidas.php';
        if (!is_file($medidasSrc)) {
            throw new \RuntimeException("No se encontró medidas.php en $medidasSrc");
        }
        copy($medidasSrc, self::$tmpDir . '/medidas.php');

        // 3) Config de pruebas (SQLite) + función NOW()
        self::$dbFile = self::$tmpDir . '/testing.sqlite';
        $configPhp = <<<PHP
        <?php
        declare(strict_types=1);
        \$pdo = new PDO('sqlite:' . __DIR__ . '/testing.sqlite');
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Simular NOW() de MySQL en SQLite
        \$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));

        // Esquema records
        \$pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS records (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          fecha TEXT NOT NULL,
          peso REAL NOT NULL,
          altura REAL NOT NULL,
          imc REAL NULL,
          created_at TEXT NOT NULL,
          updated_at TEXT NULL,
          deleted_at TEXT NULL
        );
        SQL);
        PHP;
        file_put_contents(self::$tmpDir . '/config.php', $configPhp);

        // 4) Servidor PHP embebido
        self::$port = random_int(8100, 8999);
        $cmd = sprintf('php -S 127.0.0.1:%d -t %s', self::$port, escapeshellarg(self::$tmpDir));
        self::$serverProc = proc_open($cmd, [
            0 => ['pipe','r'],
            1 => ['file', self::$tmpDir.'/server.out', 'w'],
            2 => ['file', self::$tmpDir.'/server.err', 'w'],
        ], $pipes, self::$tmpDir);
        if (!\is_resource(self::$serverProc)) {
            throw new \RuntimeException('No se pudo iniciar el servidor embebido');
        }
        usleep(300_000); // 0.3 s
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProc)) {
            proc_terminate(self::$serverProc);
            proc_close(self::$serverProc);
        }
        @unlink(self::$tmpDir . '/testing.sqlite');
        @unlink(self::$tmpDir . '/config.php');
        @unlink(self::$tmpDir . '/medidas.php');
        @unlink(self::$tmpDir . '/server.out');
        @unlink(self::$tmpDir . '/server.err');
        @rmdir(self::$tmpDir);
    }

    private function baseUrl(string $path = '/medidas.php', array $q = []): string
    {
        $qs = $q ? ('?' . http_build_query($q)) : '';
        return sprintf('http://127.0.0.1:%d%s%s', self::$port, $path, $qs);
    }

    private function httpGet(array $q, int &$code = null): array
    {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
        $resp = file_get_contents($this->baseUrl('/medidas.php', $q), false, $ctx);
        $code = $this->extractCode($http_response_header ?? []);
        return $this->decode($resp);
    }

    private function httpPost(array|string $payload, int &$code = null): array
    {
        $json = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'ignore_errors' => true
            ]
        ]);
        $resp = file_get_contents($this->baseUrl(), false, $ctx);
        $code = $this->extractCode($http_response_header ?? []);
        return $this->decode($resp);
    }

    private function httpDelete(array $q, int &$code = null): array
    {
        $ctx = stream_context_create(['http' => ['method' => 'DELETE', 'ignore_errors' => true]]);
        $resp = file_get_contents($this->baseUrl('/medidas.php', $q), false, $ctx);
        $code = $this->extractCode($http_response_header ?? []);
        return $this->decode($resp);
    }

    private function extractCode(array $hdrs): int
    {
        foreach ($hdrs as $h) if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) return (int)$m[1];
        return 0;
    }

    private function decode(?string $body): array
    {
        $d = json_decode($body ?: 'null', true);
        return is_array($d) ? $d : ['_raw' => $body];
    }

    /** GET sin user_id -> 401 */
    public function testListRequiresUserId(): void
    {
        $code = 0;
        $resp = $this->httpGet([], $code);
        $this->assertSame(401, $code);
        $this->assertSame('Parámetro user_id requerido', $resp['error'] ?? null);
    }

    /** GET con user_id sin datos -> ok y array vacío */
    public function testListEmpty(): void
    {
        $code = 0;
        $resp = $this->httpGet(['user_id' => 1], $code);
        $this->assertSame(200, $code);
        $this->assertTrue($resp['ok'] ?? false);
        $this->assertIsArray($resp['records'] ?? null);
        $this->assertCount(0, $resp['records']);
    }

    /** POST español (con altura explícita) */
    public function testInsertSpanishKeys(): void
    {
        $code = 0;
        $payload = ['user_id'=>1,'fecha'=>'2025-11-10','peso'=>82.4,'altura'=>1.78];
        $resp = $this->httpPost($payload, $code);

        $this->assertSame(201, $code);
        $this->assertTrue($resp['ok'] ?? false);
        $this->assertIsInt($resp['id'] ?? null);

        $expectedImc = round(82.4 / (1.78*1.78), 2);
        $this->assertSame($expectedImc, (float)$resp['imc']);
    }

    /** POST inglés (sin altura -> usa 1.70 por defecto) */
    public function testInsertEnglishKeysDefaultHeight(): void
    {
        $code = 0;
        $payload = ['user_id'=>1,'date'=>'2025-11-11','weight'=>80.0];
        $resp = $this->httpPost($payload, $code);

        $this->assertSame(201, $code);
        $this->assertTrue($resp['ok'] ?? false);

        $expectedImc = round(80.0 / (1.70*1.70), 2);
        $this->assertSame($expectedImc, (float)$resp['imc']);
    }

    /** DELETE (borrado lógico) y ver que no aparece en el listado */
    public function testSoftDelete(): void
    {
        // Creamos una medida y la borramos
        $c = 0;
        $ins = $this->httpPost(['user_id'=>2,'fecha'=>'2025-11-12','peso'=>70.0,'altura'=>1.70], $c);
        $this->assertSame(201, $c);
        $id = (int)$ins['id'];

        $c = 0;
        $del = $this->httpDelete(['id'=>$id,'user_id'=>2], $c);
        $this->assertSame(200, $c);
        $this->assertTrue($del['ok'] ?? false);
        $this->assertSame($id, (int)$del['deleted_id']);

        // Verificar que el listado no lo devuelve
        $c = 0;
        $list = $this->httpGet(['user_id'=>2], $c);
        $this->assertSame(200, $c);
        $this->assertTrue($list['ok'] ?? false);
        foreach ($list['records'] as $r) {
            $this->assertNotSame($id, (int)$r['id'], 'El registro borrado no debe listarse');
        }
    }

    /** Validaciones: JSON inválido y peso <=0 */
    public function testJsonInvalidoYPesoIncorrecto(): void
    {
        $c = 0;
        $r = $this->httpPost('{no es json', $c);
        $this->assertSame(400, $c);
        $this->assertSame('JSON inválido', $r['error'] ?? null);

        $c = 0;
        $r2 = $this->httpPost(['user_id'=>3,'fecha'=>'2025-10-01','peso'=>0], $c);
        $this->assertSame(400, $c);
        $this->assertSame('El campo "peso" es obligatorio y debe ser > 0', $r2['error'] ?? null);
    }
}
