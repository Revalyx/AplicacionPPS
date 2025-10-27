<?php
// apipps/users.php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$method = $_SERVER["REQUEST_METHOD"];

// Registro: POST {email,password,height}
if ($method === "POST") {
    $data = read_json_body();
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";
    $height = isset($data["height"]) && $data["height"] !== "" ? (int)$data["height"] : null;

    if ($email === "" || $password === "") {
        http_response_code(400);
        echo json_encode(["error" => "Email y contraseña requeridos"]);
        exit;
    }

    // Validación simple
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Email no válido"]);
        exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(["error" => "La contraseña debe tener al menos 6 caracteres"]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, height_cm) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hash, $height]);
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        // Probable email duplicado
        http_response_code(409);
        echo json_encode(["error" => "El correo ya está registrado"]);
    }
    exit;
}

// Cualquier otro método → 405
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
