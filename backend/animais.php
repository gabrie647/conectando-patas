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

// Um animal específico: /backend/animais.php?id=ID (usado na página de solicitação de adoção)
if (isset($_GET["id"])) {
    $idAnimal = filter_var($_GET["id"], FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare("SELECT id, descricao, localizacao, criado_em FROM registros_pets
                               WHERE id = :id AND status = 'aprovado' AND foto_dados IS NOT NULL");
        $stmt->bindValue(":id", $idAnimal ?: 0, PDO::PARAM_INT);
        $stmt->execute();
        $linha = $stmt->fetch();
    } catch (Throwable $e) {
        error_log("Erro ao buscar animal: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível carregar o animal agora."]);
        exit;
    }
    if (!$linha) {
        http_response_code(404);
        echo json_encode(["sucesso" => false, "mensagem" => "Animal não encontrado ou já adotado."]);
        exit;
    }
    echo json_encode(["sucesso" => true, "animal" => [
        "id" => (int)$linha["id"],
        "descricao" => $linha["descricao"],
        "localizacao" => $linha["localizacao"],
        "criado_em" => $linha["criado_em"],
        "foto" => "/backend/foto.php?id=" . (int)$linha["id"]
    ]]);
    exit;
}

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
