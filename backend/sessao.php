<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/auth.php';

$usuario = usuario_atual();
echo json_encode([
    'logado' => $usuario !== null,
    'usuario' => $usuario,
    'admin' => eh_admin($usuario)
]);
