<?php
// apipps/medidas.php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$userId = (int)$_SESSION["user_id"];
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    // Permite opcionalmente ?from=YYYY-MM-DD&to=YYYY-MM-DD
    $from = $_GET["from"] ?? null;
    $to   = $_GET["to"] ?? null;

    $sql = "SELECT date, weight_kg FROM weights WHERE user_id = :uid";
    $params = [":uid" => $userId];

    if ($from) { $sql .= " AND date >= :from"; $params[":from"] = $from; }
    if ($to)   { $sql .= " AND date <= :to";   $params[":to"]   = $to;   }

    $sql .= " ORDER BY date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === "POST") {
    // Inserta/actualiza peso del día
    $data = read_json_body();
    $fecha = $data["fecha"] ?? date("Y-m-d");
    $peso  = isset($data["peso"]) ? (float)$data["peso"] : null;

    if (!$peso) {
        http_response_code(400);
        echo json_encode(["error" => "El peso es obligatorio"]);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO weights (user_id, date, weight_kg) 
         VALUES (:uid, :date, :kg)
         ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)"
    );
    $stmt->execute([
        ":uid" => $userId,
        ":date" => $fecha,
        ":kg" => $peso
    ]);

    echo json_encode(["success" => true]);
    exit;
}

// Cualquier otro método → 405
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
