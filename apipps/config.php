<?php
// apipps/config.php
declare(strict_types=1);

$DB_HOST = "localhost";
$DB_NAME = "emilio_APP_PPS";
$DB_USER = "emilio_adm";
$DB_PASS = "E.reyesVaq1993$";
$DB_PORT = 3306;

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
  error_log("[config.php] Error de conexión: " . $e->getMessage());
  header('Content-Type: application/json', true, 500);
  echo json_encode(['error' => 'Error de conexión a la base de datos']);
  exit;
}

