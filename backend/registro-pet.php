<?php
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}
require_once __DIR__ . "/conexao.php";

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$descricao = trim($_POST["mensagem"] ?? "");
$localizacao = trim($_POST["localizacao"] ?? "");
if ($nome === "" || $email === "" || $descricao === "" || $localizacao === "") {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "Preencha nome, e-mail, descrição e localização."]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
    exit;
}
if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "Envie uma foto válida do pet."]);
    exit;
}
$arquivo = $_FILES["foto"];
if ($arquivo["size"] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "A foto deve ter no máximo 5 MB."]);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipos = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
$mime = $finfo->file($arquivo["tmp_name"]);
if (!isset($tipos[$mime])) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "Formato de imagem não permitido. Use JPG, PNG, WEBP ou GIF."]);
    exit;
}
$diretorio = dirname(__DIR__) . "/uploads/pets";
if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
    http_response_code(500);
    echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível preparar o armazenamento das fotos."]);
    exit;
}
$nomeArquivo = bin2hex(random_bytes(16)) . "." . $tipos[$mime];
try {
    $stmt = $pdo->prepare("INSERT INTO registros_pets (nome_responsavel, email, descricao, localizacao, foto_arquivo) VALUES (:nome, :email, :descricao, :localizacao, :foto)");
    $stmt->execute([":nome"=>$nome, ":email"=>$email, ":descricao"=>$descricao, ":localizacao"=>$localizacao, ":foto"=>$nomeArquivo]);
    if (!move_uploaded_file($arquivo["tmp_name"], $diretorio . "/" . $nomeArquivo)) {
        $pdo->prepare("DELETE FROM registros_pets WHERE foto_arquivo = :foto")->execute([":foto"=>$nomeArquivo]);
        throw new RuntimeException("Não foi possível salvar a foto.");
    }
    echo json_encode(["sucesso"=>true, "mensagem"=>"Registro do pet salvo com sucesso!"]);
} catch (Throwable $e) {
    error_log("Erro ao registrar pet: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["sucesso"=>false, "mensagem"=>"Não foi possível salvar o registro do pet."]);
}
