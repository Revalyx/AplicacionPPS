<?php
// apipps/register.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$DEBUG = isset($_GET['debug']) ? true : false;

ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

function out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/config.php'; // Debe exponer $pdo
} catch (Throwable $e) {
  out(['error' => 'Error de conexión BD'], 500);
}

// ---------- Utilidades ----------
function norm_email(string $e): string { return mb_strtolower(trim($e)); }

// ---------- Leer body ----------
$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
if (!is_array($data)) out(['error' => 'JSON inválido'], 400);

$email = isset($data['email']) ? trim((string)$data['email']) : '';
$pass  = isset($data['password']) ? (string)$data['password'] : '';
$name  = isset($data['name']) ? trim((string)$data['name']) : null;
// --- NUEVO ---
$birthdate = isset($data['fecha_nacimiento']) ? trim((string)$data['fecha_nacimiento']) : null;
// --- /NUEVO ---

if ($email === '' || $pass === '') out(['error' => 'Email y password son obligatorios'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Email no válido'], 422);
if (mb_strlen($pass) < 6) out(['error' => 'La contraseña debe tener al menos 6 caracteres'], 422);

try {
  // ---- Inspeccionar columnas de users ----
  $cols = $pdo->prepare("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
  ");
  $cols->execute();
  $columns = $cols->fetchAll(PDO::FETCH_COLUMN);

  $hasEmail       = in_array('email', $columns, true);
  $hasEmailLower  = in_array('email_lower', $columns, true); // no la usaremos para insertar
  $hasName        = in_array('name', $columns, true) ? 'name'
                  : (in_array('full_name', $columns, true) ? 'full_name' : null);
  $hasPassHash    = in_array('password_hash', $columns, true);
  $hasPassPlain   = in_array('password', $columns, true);
  $hasApiToken    = in_array('api_token', $columns, true);
  $hasCreatedAt   = in_array('created_at', $columns, true);
  $hasUpdatedAt   = in_array('updated_at', $columns, true);
  $hasDeletedAt   = in_array('deleted_at', $columns, true);
  // --- NUEVO ---
  $hasBirthdate   = in_array('fecha_nacimiento', $columns, true);
  // --- /NUEVO ---

  if (!$hasEmail && !$hasEmailLower) out(['error' => 'La tabla users no tiene columna email'], 500);
  if (!$hasPassHash && !$hasPassPlain) out(['error' => 'La tabla users no tiene password/password_hash'], 500);

  // ---- Ver si el email ya existe (match por lower) ----
  $emailNorm = norm_email($email);
  $whereEmail = $hasEmailLower
    ? "email_lower = :e"                  // si existe (aunque sea generada) úsala para buscar
    : "LOWER(email) = :e";

  $du = $pdo->prepare("SELECT id FROM users WHERE $whereEmail" . ($hasDeletedAt ? " AND deleted_at IS NULL" : "") . " LIMIT 1");
  $du->execute([':e' => $emailNorm]);
  if ($du->fetch()) out(['error' => 'El email ya está registrado'], 409);

  // ---- Preparar INSERT dinámico (sin tocar email_lower) ----
  $colsInsert = [];
  $valsInsert = [];
  $params     = [];

  if ($hasEmail) { $colsInsert[] = 'email'; $valsInsert[] = ':email'; $params[':email'] = $email; }

  if ($hasName && $name !== null && $name !== '') {
    $colsInsert[] = $hasName; $valsInsert[] = ':name'; $params[':name'] = $name;
  }
  
  // --- NUEVO ---
  if ($hasBirthdate && $birthdate !== null && $birthdate !== '') {
    $colsInsert[] = 'fecha_nacimiento'; $valsInsert[] = ':bdate'; $params[':bdate'] = $birthdate;
  }
  // --- /NUEVO ---

  if ($hasPassHash) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $colsInsert[] = 'password_hash'; $valsInsert[] = ':ph'; $params[':ph'] = $hash;
  } elseif ($hasPassPlain) {
    $colsInsert[] = 'password'; $valsInsert[] = ':pp'; $params[':pp'] = $pass;
  }

  if ($hasApiToken) {
    $token = bin2hex(random_bytes(24));
    $colsInsert[] = 'api_token'; $valsInsert[] = ':tok'; $params[':tok'] = $token;
  } else {
    $token = null;
  }

  if ($hasCreatedAt) { $colsInsert[] = 'created_at'; $valsInsert[] = 'NOW()'; }
  if ($hasUpdatedAt) { $colsInsert[] = 'updated_at'; $valsInsert[] = 'NOW()'; }

  if (empty($colsInsert)) out(['error' => 'No hay columnas válidas para insertar'], 500);

  $sql = "INSERT INTO users (" . implode(',', $colsInsert) . ")
          VALUES (" . implode(',', $valsInsert) . ")";
  $ins = $pdo->prepare($sql);
  $ins->execute($params);

  $id = (int)$pdo->lastInsertId();

  $respUser = ['id' => $id, 'email' => $email];
  if ($hasName && $name) $respUser['name'] = $name;
  // --- NUEVO ---
  if ($hasBirthdate && $birthdate) $respUser['fecha_nacimiento'] = $birthdate;
  // --- /NUEVO ---

  out([
    'ok'   => true,
    'user' => $respUser,
    'token'=> $token,           // si existe columna api_token lo devolvemos
    'msg'  => 'Usuario creado'
  ], 201);

} catch (Throwable $e) {
  error_log('[register.php] ' . $e->getMessage());
  $msg = $DEBUG ? $e->getMessage() : 'Error servidor';
  out(['error' => $msg], 500);
}
