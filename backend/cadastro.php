<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Método não permitido."
    ]);

    exit;
}

$nome = trim($_POST["nome"] ?? "");
$sobrenome = trim($_POST["sobrenome"] ?? "");
$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmarSenha"] ?? "";
$cidade = trim($_POST["cidade"] ?? "");
$bairro = trim($_POST["bairro"] ?? "");
$numero = trim($_POST["numero"] ?? "");
$termos = $_POST["termos"] ?? "";


/* ==============================
   VALIDAÇÃO DOS CAMPOS
============================== */

if (
    $nome === "" ||
    $sobrenome === "" ||
    $email === "" ||
    $senha === "" ||
    $confirmarSenha === "" ||
    $cidade === "" ||
    $bairro === "" ||
    $numero === ""
) {

    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Preencha todos os campos."
    ]);

    exit;
}


/* ==============================
   VALIDAÇÃO DO E-MAIL
============================== */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Digite um e-mail válido."
    ]);

    exit;
}


/* ==============================
   VALIDAÇÃO DA SENHA
============================== */

if ($senha !== $confirmarSenha) {

    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "As senhas não são iguais."
    ]);

    exit;
}


/* ==============================
   TERMOS
============================== */

if ($termos !== "true") {

    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Você precisa aceitar os termos."
    ]);

    exit;
}


/* ==============================
   CRIPTOGRAFAR SENHA
============================== */

$senhaHash = password_hash(
    $senha,
    PASSWORD_DEFAULT
);


/* ==============================
   INSERIR NO BANCO
============================== */

try {

    $sql = "
        INSERT INTO usuarios (
            nome,
            sobrenome,
            email,
            senha,
            cidade,
            bairro,
            numero,
            termos_aceitos
        )
        VALUES (
            :nome,
            :sobrenome,
            :email,
            :senha,
            :cidade,
            :bairro,
            :numero,
            :termos
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":nome" => $nome,
        ":sobrenome" => $sobrenome,
        ":email" => $email,
        ":senha" => $senhaHash,
        ":cidade" => $cidade,
        ":bairro" => $bairro,
        ":numero" => $numero,
        ":termos" => true
    ]);


    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Cadastro realizado com sucesso!"
    ]);

} catch (PDOException $e) {

    if ($e->getCode() === "23505") {

        http_response_code(409);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Este e-mail já está cadastrado."
        ]);

        exit;
    }


    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao salvar o cadastro."
    ]);

    exit;
}