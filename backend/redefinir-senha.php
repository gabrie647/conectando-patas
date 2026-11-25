<?php
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
require_once __DIR__ . "/protecao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
require_once __DIR__ . "/conexao.php";
csrf_exigir();

$token = trim($_POST["token"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmar = $_POST["confirmarSenha"] ?? "";

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    responder(400, ["sucesso" => false, "mensagem" => "Link inválido ou expirado. Peça um novo."]);
}
$erroSenha = validar_senha($senha);
if ($erroSenha !== null) {
    responder(400, ["sucesso" => false, "mensagem" => $erroSenha]);
}
if ($senha !== $confirmar) {
    responder(400, ["sucesso" => false, "mensagem" => "As senhas não são iguais."]);
}

try {
    if (limite_excedido($pdo, "redefinir", 10, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Muitas tentativas. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "redefinir");

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT id, usuario_id FROM redefinicoes_senha
         WHERE token_hash = :hash AND usado = FALSE AND expira_em > NOW() FOR UPDATE"
    );
    $stmt->execute([":hash" => hash("sha256", $token)]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $pdo->rollBack();
        responder(400, ["sucesso" => false, "mensagem" => "Link inválido ou expirado. Peça um novo."]);
    }

    $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id")
        ->execute([":senha" => password_hash($senha, PASSWORD_DEFAULT), ":id" => $pedido["usuario_id"]]);
    $pdo->prepare("UPDATE redefinicoes_senha SET usado = TRUE WHERE id = :id")
        ->execute([":id" => $pedido["id"]]);
    // Invalida todos os outros pedidos de redefinição pendentes deste usuário.
    $pdo->prepare("UPDATE redefinicoes_senha SET usado = TRUE WHERE usuario_id = :id AND usado = FALSE")
        ->execute([":id" => $pedido["usuario_id"]]);
    $pdo->commit();

    echo json_encode(["sucesso" => true, "mensagem" => "Senha alterada com sucesso! Você já pode entrar."]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro em redefinir-senha: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível alterar a senha agora."]);
}
