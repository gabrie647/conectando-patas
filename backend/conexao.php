<?php
$databaseUrl = getenv("DATABASE_URL");
if (!$databaseUrl) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["sucesso" => false, "mensagem" => "DATABASE_URL não está configurada no servidor."]);
    exit;
}

$partes = parse_url($databaseUrl);
if (!$partes || empty($partes["host"]) || empty($partes["user"]) || !isset($partes["pass"]) || empty($partes["path"])) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["sucesso" => false, "mensagem" => "A configuração do banco de dados é inválida."]);
    exit;
}
$host = $partes["host"];
$port = $partes["port"] ?? 5432;
$user = rawurldecode($partes["user"]);
$password = rawurldecode($partes["pass"]);
$dbname = ltrim($partes["path"], "/");
$query = [];
if (!empty($partes["query"])) parse_str($partes["query"], $query);
$sslmode = $query["sslmode"] ?? "require";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Falha de conexão PostgreSQL: " . $e->getMessage());
    http_response_code(503);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível conectar ao banco de dados. Verifique a configuração do serviço."]);
    exit;
}
