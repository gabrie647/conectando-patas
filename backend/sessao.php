<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
echo json_encode([
    'logado' => isset($_SESSION['usuario']),
    'usuario' => $_SESSION['usuario'] ?? null
]);
