<?php
// Entrega o token CSRF da sessão atual para o JavaScript do site (js/seguranca.js).
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/auth.php';

echo json_encode(['token' => csrf_token()]);
