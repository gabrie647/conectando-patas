<?php

require_once __DIR__ . "/erros.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
require_once __DIR__ . "/protecao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/email.php";
csrf_exigir();

$nome = trim($_POST["nome"] ?? "");
$sobrenome = trim($_POST["sobrenome"] ?? "");
$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmarSenha"] ?? "";
$cidade = trim($_POST["cidade"] ?? "");
$bairro = trim($_POST["bairro"] ?? "");
$numero = trim($_POST["numero"] ?? "");
$termos = $_POST["termos"] ?? "";

if ($nome === "" || $sobrenome === "" || $email === "" || $senha === "" || $confirmarSenha === ""
    || $cidade === "" || $bairro === "") {
    responder(400, ["sucesso" => false, "mensagem" => "Preencha todos os campos."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}
if (mb_strlen($nome) > 100 || mb_strlen($sobrenome) > 100 || mb_strlen($email) > 150
    || mb_strlen($cidade) > 100 || mb_strlen($bairro) > 100 || mb_strlen($numero) > 20) {
    responder(400, ["sucesso" => false, "mensagem" => "Algum campo excedeu o tamanho permitido."]);
}
if ($senha !== $confirmarSenha) {
    responder(400, ["sucesso" => false, "mensagem" => "As senhas não são iguais."]);
}
$erroSenha = validar_senha($senha);
if ($erroSenha !== null) {
    responder(400, ["sucesso" => false, "mensagem" => $erroSenha]);
}
if (!in_array($termos, ["true", "1", "on"], true)) {
    responder(400, ["sucesso" => false, "mensagem" => "Você precisa aceitar os termos."]);
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);
// Com envio de e-mail configurado, a conta só fica ativa depois de confirmar o endereço.
// Sem configuração de e-mail, a conta é ativada direto (para não bloquear novos cadastros).
$exigeConfirmacao = email_configurado();

try {
    if (limite_excedido($pdo, "cadastro", 10, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Muitos cadastros em pouco tempo. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "cadastro");

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, sobrenome, email, senha, cidade, bairro, numero, termos_aceitos, email_confirmado)
         VALUES (:nome, :sobrenome, :email, :senha, :cidade, :bairro, :numero, :termos, :confirmado)
         RETURNING id"
    );
    $stmt->execute([
        ":nome" => $nome,
        ":sobrenome" => $sobrenome,
        ":email" => $email,
        ":senha" => $senhaHash,
        ":cidade" => $cidade,
        ":bairro" => $bairro,
        ":numero" => $numero === "" ? null : $numero,
        ":termos" => true,
        ":confirmado" => !$exigeConfirmacao
    ]);
    $idUsuario = (int)$stmt->fetchColumn();

    $mensagem = "Cadastro realizado com sucesso!";
    if ($exigeConfirmacao) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO confirmacoes_email (usuario_id, token_hash, expira_em)
             VALUES (:id, :hash, NOW() + INTERVAL '24 hours')"
        )->execute([":id" => $idUsuario, ":hash" => hash("sha256", $token)]);

        $link = rtrim(url_base(), "/") . "/backend/confirmar-email.php?token=" . $token;
        $html = "<p>Olá, " . htmlspecialchars($nome, ENT_QUOTES, "UTF-8") . "!</p>"
              . "<p>Para ativar sua conta no Conectando Patas, confirme seu e-mail:</p>"
              . "<p><a href=\"" . $link . "\">Confirmar meu e-mail</a></p>"
              . "<p>O link vale por 24 horas. Se você não fez este cadastro, ignore esta mensagem.</p>";

        if (!enviar_email($email, "Confirme seu e-mail - Conectando Patas", $html)) {
            $pdo->rollBack();
            responder(503, ["sucesso" => false, "mensagem" => "Não foi possível enviar o e-mail de confirmação. Tente novamente em alguns minutos."]);
        }
        $mensagem = "Cadastro realizado! Enviamos um link de confirmação para o seu e-mail. Confirme para poder entrar.";
    }
    $pdo->commit();

    // Sem confirmação de e-mail (não configurada), a pessoa já entra logada na home.
    // Com confirmação, o login acontece ao clicar no link (backend/confirmar-email.php).
    $logado = false;
    if (!$exigeConfirmacao) {
        session_regenerate_id(true);
        $_SESSION["usuario"] = [
            "id" => $idUsuario,
            "nome" => $nome,
            "sobrenome" => $sobrenome,
            "email" => $email
        ];
        $logado = true;
    }

    echo json_encode(["sucesso" => true, "mensagem" => $mensagem, "logado" => $logado]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Mesma mensagem para e-mail já cadastrado e outros erros: não revela quem tem conta.
    if ($e->getCode() === "23505") {
        responder(400, ["sucesso" => false, "mensagem" => "Não foi possível concluir o cadastro com este e-mail. Se você já tem conta, entre ou use \"Esqueci minha senha\"."]);
    }
    error_log("Erro ao cadastrar: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Erro ao salvar o cadastro."]);
}
