<?php
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/protecao.php";

$token = trim($_POST["token"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmar = $_POST["confirmarSenha"] ?? "";

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    responder(400, ["sucesso" => false, "mensagem" => "Link inválido ou expirado. Peça um novo."]);
}
if (strlen($senha) < 8) {
    responder(400, ["sucesso" => false, "mensagem" => "A senha precisa ter pelo menos 8 caracteres."]);
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
    $pdo->commit();

    echo json_encode(["sucesso" => true, "mensagem" => "Senha alterada com sucesso! Você já pode entrar."]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro em redefinir-senha: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível alterar a senha agora."]);
}
