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
$termos = $_POST["termos"] ?? "";

if ($nome === "" || $sobrenome === "" || $email === "" || $senha === "" || $confirmarSenha === ""
    || $cidade === "" || $bairro === "") {
    responder(400, ["sucesso" => false, "mensagem" => "Preencha todos os campos."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}
if (mb_strlen($nome) > 100 || mb_strlen($sobrenome) > 100 || mb_strlen($email) > 150
    || mb_strlen($cidade) > 100 || mb_strlen($bairro) > 100) {
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
        "INSERT INTO usuarios (nome, sobrenome, email, senha, cidade, bairro, termos_aceitos, email_confirmado)
         VALUES (:nome, :sobrenome, :email, :senha, :cidade, :bairro, :termos, :confirmado)
         RETURNING id"
    );
    $stmt->execute([
        ":nome" => $nome,
        ":sobrenome" => $sobrenome,
        ":email" => $email,
        ":senha" => $senhaHash,
        ":cidade" => $cidade,
        ":bairro" => $bairro,
        ":termos" => true,
        ":confirmado" => !$exigeConfirmacao
    ]);
    $idUsuario = (int)$stmt->fetchColumn();

    $mensagem = "Cadastro realizado com sucesso!";
    $token = $exigeConfirmacao ? gerar_token_confirmacao($pdo, $idUsuario) : null;
    $pdo->commit();

    // A conta já está criada. Se o e-mail falhar, o cadastro não é desfeito:
    // o link é reenviado automaticamente quando a pessoa tentar entrar.
    if ($exigeConfirmacao) {
        $emailEnviado = enviar_confirmacao_email($nome, $email, $token);
        $mensagem = $emailEnviado
            ? "Cadastro realizado! Você já está logado. Enviamos um link de confirmação para o seu e-mail: confirme para liberar favoritos, adoções e cadastro de animais."
            : "Cadastro realizado! Você já está logado, mas não conseguimos enviar o e-mail de confirmação agora. Saia e entre novamente em alguns minutos para receber um novo link.";
    }

    // A pessoa entra logada logo após o cadastro. Enquanto o e-mail não for confirmado,
    // a sessão fica marcada como não confirmada e as ações que exigem e-mail confirmado ficam bloqueadas.
    session_regenerate_id(true);
    $_SESSION["usuario"] = [
        "id" => $idUsuario,
        "nome" => $nome,
        "sobrenome" => $sobrenome,
        "email" => $email,
        "confirmado" => !$exigeConfirmacao
    ];
    $logado = true;

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
