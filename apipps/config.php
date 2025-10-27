<?php
$DB_HOST = 'localhost';
$DB_PORT = 3306;
$DB_NAME = 'emilio_APP_PPS';
$DB_USER = 'emilio_adm';
$DB_PASS = 'E.reyesVaq1993$';

function db(): PDO {
  global $DB_HOST,$DB_PORT,$DB_NAME,$DB_USER,$DB_PASS;
  $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  return $pdo;
}

// CORS y JSON headers
function cors_json() {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}