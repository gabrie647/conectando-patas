<?php
// Lista pública dos pets aprovados: /backend/animais.php?pagina=1&q=texto
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

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
        $stmt = $pdo->prepare("SELECT id, descricao, localizacao, criado_em, nome_animal, especie, idade FROM registros_pets
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
        "nome_animal" => $linha["nome_animal"],
        "especie" => $linha["especie"],
        "idade" => $linha["idade"],
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
    $where .= " AND (descricao ILIKE :q_descricao OR localizacao ILIKE :q_localizacao OR nome_animal ILIKE :q_nome)";
    $termoBusca = "%" . addcslashes($busca, "%_\\\\") . "%";
    $params[":q_descricao"] = $termoBusca;
    $params[":q_localizacao"] = $termoBusca;
    $params[":q_nome"] = $termoBusca;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, descricao, localizacao, criado_em, nome_animal, especie, idade FROM registros_pets
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
        "nome_animal" => $l["nome_animal"],
        "especie" => $l["especie"],
        "idade" => $l["idade"],
        "criado_em" => $l["criado_em"],
        "foto" => "/backend/foto.php?id=" . (int)$l["id"]
    ], $linhas);

    echo json_encode(["sucesso" => true, "itens" => $itens, "tem_mais" => $temMais, "pagina" => $pagina]);
} catch (Throwable $e) {
    error_log("Erro ao listar animais: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível carregar os animais agora."]);
}
