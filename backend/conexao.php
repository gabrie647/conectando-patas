<?php
// Conexão com o PostgreSQL. Ao visitante vão mensagens genéricas;
// os detalhes técnicos ficam só no log do servidor.
$databaseUrl = getenv("DATABASE_URL");
if (!$databaseUrl) {
    error_log("Configuração ausente: DATABASE_URL não definida.");
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["sucesso" => false, "mensagem" => "Serviço temporariamente indisponível."]);
    exit;
}

$partes = parse_url($databaseUrl);
if (!$partes || empty($partes["host"]) || empty($partes["user"]) || !isset($partes["pass"]) || empty($partes["path"])) {
    error_log("Configuração inválida: DATABASE_URL mal formada.");
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["sucesso" => false, "mensagem" => "Serviço temporariamente indisponível."]);
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
    echo json_encode(["sucesso" => false, "mensagem" => "Serviço temporariamente indisponível."]);
    exit;
}
