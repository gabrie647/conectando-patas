<?php
// Saúde do sistema: 200 quando o banco responde, 503 quando não.
// Não expõe valores de configuração nem detalhes de erro.
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
require_once __DIR__ . "/config.php";

if (getenv("DATABASE_URL") === false || trim((string)getenv("DATABASE_URL")) === "") {
    http_response_code(503);
    echo json_encode(["ok" => false, "banco" => "nao_configurado", "configuracao_pendente" => count(configuracao_pendente())]);
    exit;
}

$bancoOk = false;
try {
    require_once __DIR__ . "/conexao.php";   // encerra com JSON próprio se a conexão falhar
    $pdo->query("SELECT 1");
    $bancoOk = true;
} catch (Throwable $e) {
    error_log("Health check: banco indisponível: " . $e->getMessage());
}

http_response_code($bancoOk ? 200 : 503);
echo json_encode([
    "ok" => $bancoOk,
    "banco" => $bancoOk ? "ok" : "indisponivel",
    "configuracao_pendente" => count(configuracao_pendente()),
    "hora" => gmdate("c")
]);
