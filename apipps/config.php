<?php
// apipps/config.php
// Configura aquí tus credenciales de MySQL/MariaDB
$DB_HOST = "localhost";
$DB_NAME = "emilio_APP_PPS";
$DB_USER = "emilio_adm";
$DB_PASS = "E.reyesVaq1993$";

// Conexión PDO compartida
try {
    $conn = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Error de conexión a la base de datos"]);
    exit;
}

// Función para leer JSON del body de la request
function read_json_body() {
    $raw = file_get_contents("php://input");
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
