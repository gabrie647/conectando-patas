<?php
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/email.php";

if (!email_configurado()) {
    responder(503, ["sucesso" => false, "mensagem" => "A recuperação de senha por e-mail ainda não foi configurada neste servidor."]);
}

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/protecao.php";

$email = trim($_POST["email"] ?? "");
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}

// Resposta igual exista o e-mail ou não, para não revelar quem tem conta.
$resposta = ["sucesso" => true, "mensagem" => "Se esse e-mail estiver cadastrado, enviamos um link para criar uma nova senha. Ele vale por 1 hora."];

try {
    if (limite_excedido($pdo, "esqueci", 5, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Muitos pedidos em pouco tempo. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "esqueci");

    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1");
    $stmt->execute([":email" => $email]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE redefinicoes_senha SET usado = TRUE WHERE usuario_id = :id AND usado = FALSE")
            ->execute([":id" => $usuario["id"]]);
        $pdo->prepare("INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em) VALUES (:id, :hash, NOW() + INTERVAL '1 hour')")
            ->execute([":id" => $usuario["id"], ":hash" => hash("sha256", $token)]);

        $link = url_base() . "/login/redefinir.html#token=" . $token;
        $nome = htmlspecialchars($usuario["nome"], ENT_QUOTES, "UTF-8");
        enviar_email(
            $email,
            "Criar nova senha - Conectando Patas",
            "<p>Olá, {$nome}!</p><p>Recebemos um pedido para criar uma nova senha. Clique no link abaixo (vale por 1 hora):</p>"
            . "<p><a href=\"{$link}\">Criar nova senha</a></p>"
            . "<p>Se não foi você, ignore este e-mail.</p>"
        );
    }
    echo json_encode($resposta);
} catch (Throwable $e) {
    error_log("Erro em esqueci-senha: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível processar o pedido agora."]);
}
