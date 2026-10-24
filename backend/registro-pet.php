<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/protecao.php";

$mensagemOk = "Registro enviado! Ele será publicado no site depois da análise da nossa equipe.";

// Robô: finge sucesso e não salva nada.
if (honeypot_preenchido()) {
    echo json_encode(["sucesso" => true, "mensagem" => $mensagemOk]);
    exit;
}

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$descricao = trim($_POST["mensagem"] ?? "");
$localizacao = trim($_POST["localizacao"] ?? "");

if ($nome === "" || $email === "" || $descricao === "" || $localizacao === "") {
    responder(400, ["sucesso" => false, "mensagem" => "Preencha nome, e-mail, descrição e localização."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}
if (mb_strlen($nome) > 150 || mb_strlen($email) > 150 || mb_strlen($localizacao) > 255 || mb_strlen($descricao) > 2000) {
    responder(400, ["sucesso" => false, "mensagem" => "Algum campo excedeu o tamanho permitido."]);
}
if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
    responder(400, ["sucesso" => false, "mensagem" => "Envie uma foto válida do pet."]);
}

$arquivo = $_FILES["foto"];
if ($arquivo["size"] > 5 * 1024 * 1024) {
    responder(400, ["sucesso" => false, "mensagem" => "A foto deve ter no máximo 5 MB."]);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipos = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
$mime = $finfo->file($arquivo["tmp_name"]);
if (!isset($tipos[$mime])) {
    responder(400, ["sucesso" => false, "mensagem" => "Formato de imagem não permitido. Use JPG, PNG, WEBP ou GIF."]);
}

$nomeArquivo = bin2hex(random_bytes(16)) . "." . $tipos[$mime];
$fp = null;
try {
    if (limite_excedido($pdo, "pet", 5, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Você enviou muitos registros em pouco tempo. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "pet");

    $fp = fopen($arquivo["tmp_name"], "rb");
    if ($fp === false) {
        throw new RuntimeException("Não foi possível ler a foto enviada.");
    }
    $stmt = $pdo->prepare("INSERT INTO registros_pets (nome_responsavel, email, descricao, localizacao, foto_arquivo, foto_tipo, foto_dados, status) VALUES (:nome, :email, :descricao, :localizacao, :foto, :tipo, :dados, 'pendente')");
    $stmt->bindValue(":nome", $nome);
    $stmt->bindValue(":email", $email);
    $stmt->bindValue(":descricao", $descricao);
    $stmt->bindValue(":localizacao", $localizacao);
    $stmt->bindValue(":foto", $nomeArquivo);
    $stmt->bindValue(":tipo", $mime);
    $stmt->bindParam(":dados", $fp, PDO::PARAM_LOB);
    $stmt->execute();
    echo json_encode(["sucesso" => true, "mensagem" => $mensagemOk]);
} catch (Throwable $e) {
    error_log("Erro ao registrar pet: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível salvar o registro do pet."]);
} finally {
    if (is_resource($fp)) {
        fclose($fp);
    }
}
