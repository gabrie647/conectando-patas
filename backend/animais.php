<?php
// Lista pública dos pets aprovados: /backend/animais.php?pagina=1&q=texto
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: public, max-age=60");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/conexao.php";

$porPagina = 12;
$pagina = max(1, (int)($_GET["pagina"] ?? 1));
$busca = mb_substr(trim($_GET["q"] ?? ""), 0, 80);

$where = "status = 'aprovado' AND foto_dados IS NOT NULL";
$params = [];
if ($busca !== "") {
    $where .= " AND (descricao ILIKE :q OR localizacao ILIKE :q)";
    $params[":q"] = "%" . addcslashes($busca, "%_\\") . "%";
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, descricao, localizacao, criado_em FROM registros_pets
         WHERE $where ORDER BY criado_em DESC, id DESC LIMIT :limite OFFSET :deslocamento"
    );
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor);
    }
    $stmt->bindValue(":limite", $porPagina + 1, PDO::PARAM_INT);
    $stmt->bindValue(":deslocamento", ($pagina - 1) * $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $linhas = $stmt->fetchAll();

    $temMais = count($linhas) > $porPagina;
    $linhas = array_slice($linhas, 0, $porPagina);

    $itens = array_map(fn($l) => [
        "id" => (int)$l["id"],
        "descricao" => $l["descricao"],
        "localizacao" => $l["localizacao"],
        "criado_em" => $l["criado_em"],
        "foto" => "/backend/foto.php?id=" . (int)$l["id"]
    ], $linhas);

    echo json_encode(["sucesso" => true, "itens" => $itens, "tem_mais" => $temMais, "pagina" => $pagina]);
} catch (Throwable $e) {
    error_log("Erro ao listar animais: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível carregar os animais agora."]);
}
