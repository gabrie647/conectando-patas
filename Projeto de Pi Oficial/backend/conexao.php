<?php
// Tenta pegar a URL do banco de dados das variáveis de ambiente do Render
$db_url = getenv('DATABASE_URL');

if ($db_url) {
    // Se estiver no Render
    $dbopts = parse_url($db_url);
    $host = $dbopts["host"];
    $port = $dbopts["port"] ?? 5432;
    $user = $dbopts["user"];
    $password = $dbopts["pass"];
    $dbname = ltrim($dbopts["path"], '/');
} else {
    // Configurações locais (fallback para quando rodar no seu computador)
    $host = "localhost";
    $port = 5432;
    $user = "seu_usuario_local";
    $password = "sua_senha_local";
    $dbname = "seu_banco_local";
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>