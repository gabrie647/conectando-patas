<?php
// Endpoint do painel administrativo. Só responde a usuários listados em ADMIN_EMAILS.
header("Cache-Control: no-store");
require_once __DIR__ . "/protecao.php";

$admin = exigir_admin();

require_once __DIR__ . "/conexao.php";

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo !== "GET" && $metodo !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
if ($metodo === "POST") {
    // Ações que alteram dados só aceitam pedidos do próprio painel, com o token CSRF válido.
    if (($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !== "fetch") {
        responder(403, ["sucesso" => false, "mensagem" => "Pedido não permitido."]);
    }
    csrf_exigir();
}

$acao = $metodo === "POST" ? ($_POST["acao"] ?? "") : ($_GET["acao"] ?? "");
$porPagina = 20;

function pagina_recebida(): int
{
    return min(10000, max(1, (int)($_GET["pagina"] ?? 1)));
}

function id_recebido(): int
{
    $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        responder(400, ["sucesso" => false, "mensagem" => "Registro inválido."]);
    }
    return $id;
}

// Guarda quem fez cada alteração no painel (trilha de auditoria).
function registrar_auditoria(PDO $pdo, array $admin, string $acao, string $alvo): void
{
    $stmt = $pdo->prepare("INSERT INTO auditoria (admin_email, acao, alvo, ip_hash) VALUES (:email, :acao, :alvo, :hash)");
    $stmt->execute([
        ":email" => $admin["email"],
        ":acao" => $acao,
        ":alvo" => $alvo,
        ":hash" => ip_hash()
    ]);
}

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
            $pagina = pagina_recebida();
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
            $pagina = pagina_recebida();
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
            $id = id_recebido();
            $lida = ($_POST["lida"] ?? "1") === "1" ? "TRUE" : "FALSE";
            $pdo->prepare("UPDATE contatos SET lida = $lida WHERE id = :id")->execute([":id" => $id]);
            registrar_auditoria($pdo, $admin, "mensagem_lida", "contato:$id");
            responder(200, ["sucesso" => true]);

        case "mensagem_excluir":
            $id = id_recebido();
            $pdo->prepare("DELETE FROM contatos WHERE id = :id")->execute([":id" => $id]);
            registrar_auditoria($pdo, $admin, "mensagem_excluir", "contato:$id");
            responder(200, ["sucesso" => true]);

        case "pet_status":
            $status = $_POST["status"] ?? "";
            if (!in_array($status, ["pendente", "aprovado", "recusado"], true)) {
                responder(400, ["sucesso" => false, "mensagem" => "Status inválido."]);
            }
            $id = id_recebido();
            $pdo->prepare("UPDATE registros_pets SET status = :status WHERE id = :id")
                ->execute([":status" => $status, ":id" => $id]);
            registrar_auditoria($pdo, $admin, "pet_status:$status", "registro:$id");
            responder(200, ["sucesso" => true]);

        case "pet_excluir":
            $id = id_recebido();
            $pdo->prepare("DELETE FROM registros_pets WHERE id = :id")->execute([":id" => $id]);
            registrar_auditoria($pdo, $admin, "pet_excluir", "registro:$id");
            responder(200, ["sucesso" => true]);

        default:
            responder(400, ["sucesso" => false, "mensagem" => "Ação desconhecida."]);
    }
} catch (Throwable $e) {
    error_log("Erro no painel: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Erro ao processar o pedido."]);
}
