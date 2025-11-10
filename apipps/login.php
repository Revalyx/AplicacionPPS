<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function out($arr, int $code = 200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
if (!is_array($data)) out(['error'=>'JSON inválido'], 400);

$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';
if ($email === '' || $pass === '') out(['error'=>'Email y contraseña requeridos'], 400);

try {
  // --- MODIFICADO ---
  $stmt = $pdo->prepare("SELECT id, email, password_hash, name, fecha_nacimiento FROM users WHERE email = :e LIMIT 1");
  // --- /MODIFICADO ---
  $stmt->execute([':e' => $email]);
  $u = $stmt->fetch();

  if (!$u) out(['error'=>'Credenciales inválidas'], 401);
  if (!password_verify($pass, $u['password_hash'])) out(['error'=>'Credenciales inválidas'], 401);

  $token = bin2hex(random_bytes(24));

  // Actualiza api_token si existe esa columna
  try {
    $pdo->prepare("UPDATE users SET api_token = :t, last_login = NOW() WHERE id=:id")
        ->execute([':t'=>$token, ':id'=>(int)$u['id']]);
  } catch (Throwable $e) { /* ignorar si no existe */ }

  out([
    'ok' => true,
    'token' => $token,
    'user' => [
      'id' => (int)$u['id'],
      'email' => $u['email'],
      'name' => $u['name'],
      // --- NUEVO ---
      'fecha_nacimiento' => $u['fecha_nacimiento']
      // --- /NUEVO ---
    ]
  ]);
} catch (Throwable $e) {
  error_log('[login.php] ' . $e->getMessage());
  out(['error'=>'Error servidor'], 500);
}
