<?php
// apipps/users.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
if (!is_array($data)) out(['error' => 'JSON inválido'], 400);

$email = isset($data['email']) ? trim((string)$data['email']) : '';
$pass  = isset($data['password']) ? (string)$data['password'] : '';
$name  = isset($data['name']) ? trim((string)$data['name']) : '';

if ($email === '' || $pass === '') out(['error' => 'Email y contraseña requeridos'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Email inválido'], 400);
if (strlen($pass) < 6) out(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);

try {
  // comprobar existente
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email_lower = :e LIMIT 1');
  $stmt->execute([':e' => mb_strtolower($email)]);
  if ($stmt->fetch()) out(['error' => 'El email ya está registrado'], 409);

  // insertar (su tabla users admite columnas: email, email_lower, password_hash, name, created_at)
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO users (email, email_lower, password_hash, name, created_at) VALUES (:email, :email_lower, :hash, :name, NOW())');
  $stmt->execute([
    ':email' => $email,
    ':email_lower' => mb_strtolower($email),
    ':hash' => $hash,
    ':name' => $name
  ]);

  $id = (int)$pdo->lastInsertId();
  out(['ok' => true, 'id' => $id], 201);

} catch (Throwable $e) {
  out(['error' => 'Error BD'], 500);
}

