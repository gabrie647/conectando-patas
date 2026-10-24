<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/protecao.php";
require_once __DIR__ . "/email.php";

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$assunto = trim($_POST["assunto"] ?? "");
$mensagem = trim($_POST["mensagem"] ?? "");
$privacidade = $_POST["privacidade"] ?? "";

$assuntosValidos = ["adocao", "resgate", "voluntario", "duvida", "sugestao", "outro"];

// Robô: finge sucesso e não salva nada.
if (honeypot_preenchido()) {
    echo json_encode(["sucesso" => true, "mensagem" => "Mensagem enviada com sucesso! Obrigado por entrar em contato."]);
    exit;
}

if ($nome === "" || $email === "" || $assunto === "" || $mensagem === "") {
    responder(400, ["sucesso" => false, "mensagem" => "Preencha todos os campos obrigatórios."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}
if (!in_array($assunto, $assuntosValidos, true)) {
    responder(400, ["sucesso" => false, "mensagem" => "Assunto inválido."]);
}
if (!in_array($privacidade, ["true", "1", "on"], true)) {
    responder(400, ["sucesso" => false, "mensagem" => "Você precisa aceitar a política de privacidade."]);
}
if (mb_strlen($nome) > 150 || mb_strlen($email) > 150 || mb_strlen($mensagem) > 5000) {
    responder(400, ["sucesso" => false, "mensagem" => "Algum campo excedeu o tamanho permitido."]);
}

try {
    if (limite_excedido($pdo, "contato", 5, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Você enviou muitas mensagens em pouco tempo. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "contato");

    $stmt = $pdo->prepare("INSERT INTO contatos (nome, email, assunto, mensagem) VALUES (:nome, :email, :assunto, :mensagem)");
    $stmt->execute([
        ":nome" => $nome,
        ":email" => $email,
        ":assunto" => $assunto,
        ":mensagem" => $mensagem
    ]);

    // Aviso por e-mail (opcional): só se CONTACT_NOTIFY_EMAIL e o envio estiverem configurados.
    $destino = getenv("CONTACT_NOTIFY_EMAIL");
    if ($destino && email_configurado()) {
        $h = fn($t) => htmlspecialchars($t, ENT_QUOTES, "UTF-8");
        enviar_email(
            $destino,
            "Nova mensagem de contato: " . $assunto,
            "<p><strong>" . $h($nome) . "</strong> (" . $h($email) . ") enviou uma mensagem sobre <em>" . $h($assunto) . "</em>:</p>"
            . "<p>" . nl2br($h($mensagem)) . "</p>"
        );
    }

    echo json_encode(["sucesso" => true, "mensagem" => "Mensagem enviada com sucesso! Obrigado por entrar em contato."]);
} catch (Throwable $e) {
    error_log("Erro ao salvar contato: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível enviar sua mensagem. Tente novamente."]);
}
