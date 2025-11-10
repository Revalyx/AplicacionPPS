<?php
// Ejecutar una vez para crear demo@demo.com / demo123 y luego borrar
require_once __DIR__ . '/config.php';

$email = 'demo@demo.com';
$pass  = 'demo123';
$name  = 'Demo';

try {
  // Crea tabla mínima si no existe (sin definir email_lower porque ya la tienes generada)
  $conn->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(190) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      name VARCHAR(190) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Inserta sin tocar email_lower (la generará MySQL)
  $stmt = $conn->prepare("
    INSERT INTO users (email, password_hash, name)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE name = VALUES(name)
  ");
  $stmt->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $name]);

  echo json_encode(["ok" => true, "email" => $email, "pass" => $pass]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

