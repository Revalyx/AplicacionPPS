<?php
// api/users.php
require __DIR__ . '/config.php';
cors_json(); // CORS + JSON headers básicos

$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

try {
  // ==========================================================
  // GET → Listar usuarios (sin password_hash y sin borrados)
  // ==========================================================
  if ($method === 'GET') {
    // Opcional: ?order=asc|desc  (por id)
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

    $stmt = $pdo->query("
      SELECT id, name, email, created_at, last_login, failed_logins, locked_until
      FROM users
      WHERE deleted_at IS NULL
      ORDER BY id $order
    ");

    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ==========================================================
  // POST → Crear usuario
  // JSON: { "name":"...", "email":"...", "password":"..." }
  // Nota: NO insertamos email_lower si es columna generada.
  // ==========================================================
  if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $name  = trim($input['name']  ?? '');
    $email = trim($input['email'] ?? '');
    $pass  = (string)($input['password'] ?? '');

    // Validación mínima
    if ($name === '' || $email === '' || $pass === '') {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'Faltan campos: name, email, password']);
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'Email no válido']);
      exit;
    }
    if (strlen($pass) < 6) {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'La contraseña debe tener al menos 6 caracteres']);
      exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Importante: no enviar email_lower si es columna generada en la BD
    $sql = "
      INSERT INTO users (email, password_hash, name, created_at, failed_logins)
      VALUES (:e, :h, :n, NOW(), 0)
    ";
    $stmt = $pdo->prepare($sql);

    try {
      $stmt->execute([
        ':e' => $email,
        ':h' => $hash,
        ':n' => $name,
      ]);

      echo json_encode([
        'ok'   => true,
        'data' => [
          'id'    => (int)$pdo->lastInsertId(),
          'name'  => $name,
          'email' => $email
        ]
      ]);
    } catch (PDOException $ex) {
      // 1062 = clave duplicada (probablemente UNIQUE en email o email_lower)
      if (isset($ex->errorInfo[1]) && (int)$ex->errorInfo[1] === 1062) {
        http_response_code(409);
        echo json_encode(['ok'=>false, 'error'=>'El email ya está registrado']);
      } else {
        throw $ex;
      }
    }
    exit;
  }

  // ==========================================================
  // DELETE → Borrado lógico por id
  // /users.php?id=123
  // ==========================================================
  if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'Falta parámetro id']);
      exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);

    echo json_encode(['ok'=>true, 'data'=>['deleted'=>$stmt->rowCount()]]);
    exit;
  }

  // ==========================================================
  // Método no permitido
  // ==========================================================
  http_response_code(405);
  echo json_encode(['ok'=>false, 'error'=>'Método no permitido']);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Error interno', 'meta'=>$e->getMessage()]);
}
