<?php
// apipps/login.php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$method = $_SERVER["REQUEST_METHOD"];

// Permitir comprobar sesión actual: GET → {logged:true/false, user?}
if ($method === "GET") {
    if (isset($_SESSION["user_id"])) {
        echo json_encode([
            "logged" => true,
            "user" => [
                "id" => $_SESSION["user_id"],
                "email" => $_SESSION["email"] ?? null,
                "height_cm" => $_SESSION["height_cm"] ?? null
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["logged" => false]);
    }
    exit;
}

// Autenticación: POST {email,password}
if ($method === "POST") {
    $data = read_json_body();
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";

    if ($email === "" || $password === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Email y contraseña requeridos"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, email, password_hash, height_cm FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user["password_hash"])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Credenciales inválidas"]);
        exit;
    }

    // Iniciar sesión
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["height_cm"] = $user["height_cm"];

    echo json_encode([
        "success" => true,
        "user" => [
            "id" => $user["id"],
            "email" => $user["email"],
            "height_cm" => $user["height_cm"]
        ]
    ]);
    exit;
}

// Cualquier otro método → 405
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
