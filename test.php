<?php

function test($name, $ok) {
    echo ($ok ? "✔ " : "✘ ") . $name . "<br>";
}

// Cambie por su URL real
$BASE = "http://emiliorvntz.alumnosatlantida.es/apipps";

// ---- 1) LOGIN ----
$res = file_get_contents("$BASE/login.php?email=demo@demo.com&password=demo123");
$data = json_decode($res, true);

$token = $data["token"] ?? null;

test("Login devuelve token", $token !== null);

// ---- 2) CREAR RECORD ----
$payload = json_encode([
    "fecha" => "2025-11-10",
    "peso" => 80,
    "altura" => 1.80
]);

$opts = [
    "http" => [
        "method"  => "POST",
        "header"  => "Content-Type: application/json\r\nAuthorization: Bearer $token\r\n",
        "content" => $payload
    ]
];
$res = file_get_contents("$BASE/records/create.php", false, stream_context_create($opts));

test("Crear record", $res !== false);

// ---- 3) LISTAR ----
$opts = [
    "http" => [
        "method"  => "GET",
        "header"  => "Authorization: Bearer $token\r\n"
    ]
];

$res = file_get_contents("$BASE/records/index.php", false, stream_context_create($opts));
$list = json_decode($res, true);

test("Listar devuelve registros", is_array($list) && count($list) > 0);

echo "<hr>FIN DE TESTS";
