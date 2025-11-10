<?php
// apipps/bootstrap.php
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'httponly' => true,
  'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

