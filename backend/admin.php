<?php
// Endpoint do painel administrativo. Só responde a usuários listados em ADMIN_EMAILS.
header("Cache-Control: no-store");
require_once __DIR__ . "/auth.php";

exigir_admin();

require_once __DIR__ . "/conexao.php";

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo !== "GET" && $metodo !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
// Ações que alteram dados só aceitam pedidos feitos pelo próprio painel.
if ($metodo === "POST" && ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !== "fetch") {
    responder(403, ["sucesso" => false, "mensagem" => "Pedido não permitido."]);
}

$acao = $metodo === "POST" ? ($_POST["acao"] ?? "") : ($_GET["acao"] ?? "");
$porPagina = 20;

try {
    switch ($acao) {
        case "resumo":
            $linha = $pdo->query(
                "SELECT
                    (SELECT COUNT(*) FROM contatos WHERE lida = FALSE) AS mensagens_novas,
                    (SELECT COUNT(*) FROM contatos) AS mensagens_total,
                    (SELECT COUNT(*) FROM registros_pets WHERE status = 'pendente') AS pets_pendentes,
                    (SELECT COUNT(*) FROM registros_pets WHERE status = 'aprovado') AS pets_aprovados"
            )->fetch();
            responder(200, ["sucesso" => true, "resumo" => array_map("intval", $linha)]);

        case "mensagens":
            $pagina = max(1, (int)($_GET["pagina"] ?? 1));
            $stmt = $pdo->prepare(
                "SELECT id, nome, email, assunto, mensagem, lida, criado_em FROM contatos
                 ORDER BY criado_em DESC, id DESC LIMIT :limite OFFSET :deslocamento"
            );
            $stmt->bindValue(":limite", $porPagina + 1, PDO::PARAM_INT);
            $stmt->bindValue(":deslocamento", ($pagina - 1) * $porPagina, PDO::PARAM_INT);
            $stmt->execute();
            $linhas = $stmt->fetchAll();
            responder(200, [
                "sucesso" => true,
                "itens" => array_slice($linhas, 0, $porPagina),
                "tem_mais" => count($linhas) > $porPagina
            ]);

        case "pets":
            $status = $_GET["status"] ?? "pendente";
            if (!in_array($status, ["pendente", "aprovado", "recusado"], true)) {
                responder(400, ["sucesso" => false, "mensagem" => "Status inválido."]);
            }
            $pagina = max(1, (int)($_GET["pagina"] ?? 1));
            $stmt = $pdo->prepare(
                "SELECT id, nome_responsavel, email, descricao, localizacao, status, criado_em,
                        (foto_dados IS NOT NULL) AS tem_foto
                 FROM registros_pets WHERE status = :status
                 ORDER BY criado_em DESC, id DESC LIMIT :limite OFFSET :deslocamento"
            );
            $stmt->bindValue(":status", $status);
            $stmt->bindValue(":limite", $porPagina + 1, PDO::PARAM_INT);
            $stmt->bindValue(":deslocamento", ($pagina - 1) * $porPagina, PDO::PARAM_INT);
            $stmt->execute();
            $linhas = $stmt->fetchAll();
            responder(200, [
                "sucesso" => true,
                "itens" => array_slice($linhas, 0, $porPagina),
                "tem_mais" => count($linhas) > $porPagina
            ]);

        case "mensagem_lida":
            $id = (int)($_POST["id"] ?? 0);
            $lida = ($_POST["lida"] ?? "1") === "1" ? "TRUE" : "FALSE";
            $pdo->prepare("UPDATE contatos SET lida = $lida WHERE id = :id")->execute([":id" => $id]);
            responder(200, ["sucesso" => true]);

        case "mensagem_excluir":
            $pdo->prepare("DELETE FROM contatos WHERE id = :id")->execute([":id" => (int)($_POST["id"] ?? 0)]);
            responder(200, ["sucesso" => true]);

        case "pet_status":
            $status = $_POST["status"] ?? "";
            if (!in_array($status, ["pendente", "aprovado", "recusado"], true)) {
                responder(400, ["sucesso" => false, "mensagem" => "Status inválido."]);
            }
            $pdo->prepare("UPDATE registros_pets SET status = :status WHERE id = :id")
                ->execute([":status" => $status, ":id" => (int)($_POST["id"] ?? 0)]);
            responder(200, ["sucesso" => true]);

        case "pet_excluir":
            $pdo->prepare("DELETE FROM registros_pets WHERE id = :id")->execute([":id" => (int)($_POST["id"] ?? 0)]);
            responder(200, ["sucesso" => true]);

        default:
            responder(400, ["sucesso" => false, "mensagem" => "Ação desconhecida."]);
    }
} catch (Throwable $e) {
    error_log("Erro no painel: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Erro ao processar o pedido."]);
}
