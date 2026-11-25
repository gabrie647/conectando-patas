<?php
// Favoritos da conta: ids, lista e alternar (favoritar / desfazer).
// Exige login. Alterações (POST) exigem o token CSRF.
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
require_once __DIR__ . "/protecao.php";
require_once __DIR__ . "/conexao.php";

$usuario = exigir_login();

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo !== "GET" && $metodo !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
if ($metodo === "POST") {
    csrf_exigir();
}
$acao = $metodo === "POST" ? ($_POST["acao"] ?? "") : ($_GET["acao"] ?? "");
$idUsuario = (int)$usuario["id"];

try {
    // Ids dos animais favoritados (usado para marcar os corações)
    if ($acao === "ids") {
        $stmt = $pdo->prepare("SELECT registro_pet_id FROM favoritos WHERE usuario_id = :u");
        $stmt->execute([":u" => $idUsuario]);
        $ids = array_map("intval", array_column($stmt->fetchAll(), "registro_pet_id"));
        responder(200, ["sucesso" => true, "ids" => $ids]);
    }

    // Lista completa para Minha conta
    if ($acao === "listar") {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.nome_animal, p.descricao, p.localizacao, p.status, f.criado_em
             FROM favoritos f
             JOIN registros_pets p ON p.id = f.registro_pet_id
             WHERE f.usuario_id = :u
             ORDER BY f.criado_em DESC LIMIT 100"
        );
        $stmt->execute([":u" => $idUsuario]);
        $itens = array_map(fn($l) => [
            "id" => (int)$l["id"],
            "nome_animal" => $l["nome_animal"],
            "descricao" => $l["descricao"],
            "localizacao" => $l["localizacao"],
            "status" => $l["status"],
            "favoritado_em" => $l["criado_em"]
        ], $stmt->fetchAll());
        responder(200, ["sucesso" => true, "itens" => $itens]);
    }

    // Favoritar ou desfazer
    if ($acao === "alternar") {
        $idAnimal = filter_var($_POST["animal"] ?? null, FILTER_VALIDATE_INT);
        $favoritar = filter_var($_POST["favoritar"] ?? "", FILTER_VALIDATE_BOOLEAN);
        if (!$idAnimal || $idAnimal < 1) {
            responder(400, ["sucesso" => false, "mensagem" => "Animal inválido."]);
        }

        if ($favoritar) {
            $stmt = $pdo->prepare("SELECT status FROM registros_pets WHERE id = :id");
            $stmt->execute([":id" => $idAnimal]);
            $pet = $stmt->fetch();
            if (!$pet || $pet["status"] !== "aprovado") {
                responder(404, ["sucesso" => false, "mensagem" => "Este animal não está disponível."]);
            }
            $stmt = $pdo->prepare(
                "INSERT INTO favoritos (usuario_id, registro_pet_id) VALUES (:u, :p)
                 ON CONFLICT (usuario_id, registro_pet_id) DO NOTHING"
            );
            $stmt->execute([":u" => $idUsuario, ":p" => $idAnimal]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM favoritos WHERE usuario_id = :u AND registro_pet_id = :p");
            $stmt->execute([":u" => $idUsuario, ":p" => $idAnimal]);
        }
        responder(200, ["sucesso" => true, "favorito" => $favoritar]);
    }

    responder(400, ["sucesso" => false, "mensagem" => "Ação desconhecida."]);
} catch (Throwable $e) {
    error_log("Erro em favoritos: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível atualizar seus favoritos agora."]);
}
