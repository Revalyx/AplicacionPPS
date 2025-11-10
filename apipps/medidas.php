<?php
// apipps/medidas.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {

  // ========================== LISTAR ==========================
  if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($user_id <= 0) out(['error' => 'Parámetro user_id requerido'], 401);

    $sql = "SELECT id, user_id, fecha, peso, altura, imc, created_at, updated_at
            FROM records
            WHERE user_id = :uid AND deleted_at IS NULL
            ORDER BY fecha DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    $rows = $stmt->fetchAll();

    out(['ok' => true, 'records' => $rows]);
  }

  // ========================== INSERTAR ==========================
  elseif ($method === 'POST') {
    $raw = file_get_contents('php://input');
    error_log('[medidas.php] BODY=' . $raw);
    $data = json_decode($raw ?: 'null', true);
    if (!is_array($data)) out(['error'=>'JSON inválido'], 400);

    // aceptar claves en español o inglés
    $user_id = (int)($data['user_id'] ?? 0);
    $peso    = (float)($data['peso'] ?? $data['weight'] ?? 0);
    $fecha   = trim((string)($data['fecha'] ?? $data['date'] ?? ''));
    // altura segura por defecto (en metros)
    $alturaIn = isset($data['altura']) ? (float)$data['altura'] : null;
    $altura   = ($alturaIn !== null && $alturaIn > 0) ? $alturaIn : 1.70;

    if ($user_id <= 0) out(['error' => 'user_id requerido'], 401);
    if ($peso <= 0) out(['error' => 'El campo "peso" es obligatorio y debe ser > 0'], 400);
    if ($fecha === '') $fecha = date('Y-m-d');

    // calcular IMC (2 decimales). La columna imc es NULLABLE, pero aquí lo generamos siempre.
    $imc = round($peso / ($altura * $altura), 2);

    $stmt = $pdo->prepare(
      "INSERT INTO records (user_id, fecha, peso, altura, imc, created_at, updated_at)
       VALUES (:uid, :fecha, :peso, :altura, :imc, NOW(), NOW())"
    );
    $stmt->execute([
      ':uid'    => $user_id,
      ':fecha'  => $fecha,
      ':peso'   => $peso,
      ':altura' => $altura,
      ':imc'    => $imc
    ]);

    $id = (int)$pdo->lastInsertId();
    out([
      'ok'     => true,
      'id'     => $id,
      'user_id'=> $user_id,
      'fecha'  => $fecha,
      'peso'   => $peso,
      'altura' => $altura,
      'imc'    => $imc
    ], 201);
  }

  // ========================== BORRADO LÓGICO ==========================
  elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($id <= 0 || $user_id <= 0) out(['error' => 'Parámetros id y user_id requeridos'], 400);

    $stmt = $pdo->prepare("UPDATE records SET deleted_at = NOW() WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $id, ':uid' => $user_id]);

    out(['ok' => true, 'deleted_id' => $id]);
  }

  else {
    out(['error' => 'Método no permitido'], 405);
  }

} catch (Throwable $e) {
  error_log('[medidas.php] ERROR=' . $e->getMessage());
  out(['error' => 'Error servidor', 'debug' => $e->getMessage()], 500);
}

