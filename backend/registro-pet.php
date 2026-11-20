<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/imagem.php";
require_once __DIR__ . "/protecao.php";
csrf_exigir();
$usuarioLogado = exigir_login();

// Publicação automática é o padrão solicitado para a vitrine pública.
// Para pausar temporariamente a publicação automática, configure AUTO_APROVAR=0 no Render.
$configAutoAprovar = getenv("AUTO_APROVAR");
$autoAprovar = $configAutoAprovar === false
    || trim((string)$configAutoAprovar) === ""
    || filter_var($configAutoAprovar, FILTER_VALIDATE_BOOLEAN);
$statusInicial = $autoAprovar ? "aprovado" : "pendente";
$mensagemOk = $autoAprovar
    ? "Animal cadastrado! Ele já aparece na página de animais."
    : "Registro enviado! Ele será publicado no site depois da análise da nossa equipe.";

// Robô: finge sucesso e não salva nada.
if (honeypot_preenchido()) {
    echo json_encode(["sucesso" => true, "mensagem" => $mensagemOk]);
    exit;
}

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$descricao = trim($_POST["mensagem"] ?? "");
$localizacao = trim($_POST["localizacao"] ?? "");

// Informações do animal (aparecem no card da página de animais)
$nomeAnimal = trim($_POST["nome_animal"] ?? "");
$especie = $_POST["especie"] ?? "";
$idade = $_POST["idade"] ?? "";
if (mb_strlen($nomeAnimal) > 100) {
    responder(400, ["sucesso" => false, "mensagem" => "O nome do animal pode ter no máximo 100 caracteres."]);
}
if (!in_array($especie, ["cao", "gato", "outro"], true)) {
    responder(400, ["sucesso" => false, "mensagem" => "Informe a espécie do pet."]);
}
if (!in_array($idade, ["filhote", "adulto", "idoso"], true)) {
    responder(400, ["sucesso" => false, "mensagem" => "Informe a idade do pet."]);
}
$nomeAnimal = $nomeAnimal === "" ? null : $nomeAnimal;

// Coordenadas do GPS (opcionais). Guardamos só a localização APROXIMADA (3 casas ≈ 110 m).
$latitude = null;
$longitude = null;
if (isset($_POST["latitude"], $_POST["longitude"]) && $_POST["latitude"] !== "" && $_POST["longitude"] !== "") {
    $lat = filter_var($_POST["latitude"], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($_POST["longitude"], FILTER_VALIDATE_FLOAT);
    if ($lat !== false && $lng !== false && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
        $latitude = round($lat, 3);
        $longitude = round($lng, 3);
    }
}

if ($nome === "" || $email === "" || $descricao === "" || $localizacao === "") {
    responder(400, ["sucesso" => false, "mensagem" => "Preencha nome, e-mail, descrição e localização."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, ["sucesso" => false, "mensagem" => "Digite um e-mail válido."]);
}
if (mb_strlen($nome) > 150 || mb_strlen($email) > 150 || mb_strlen($localizacao) > 255 || mb_strlen($descricao) > 2000) {
    responder(400, ["sucesso" => false, "mensagem" => "Algum campo excedeu o tamanho permitido."]);
}
$erroUpload = $_FILES["foto"]["error"] ?? UPLOAD_ERR_NO_FILE;
if ($erroUpload !== UPLOAD_ERR_OK) {
    $mensagemUpload = match ($erroUpload) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "A foto é grande demais. O limite é 5 MB.",
        UPLOAD_ERR_PARTIAL => "O envio da foto foi interrompido. Tente novamente.",
        default => "Envie uma foto válida do pet.",
    };
    responder(400, ["sucesso" => false, "mensagem" => $mensagemUpload]);
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

// Recodifica a imagem: confere o conteúdo real, remove metadados (ex.: GPS) e recusa o que não for imagem.
$imagemSegura = imagem_segura($arquivo["tmp_name"], $mime);
if ($imagemSegura === null) {
    responder(400, ["sucesso" => false, "mensagem" => "Não foi possível usar esta imagem. Envie uma foto JPG, PNG, WEBP ou GIF válida, de até 16 megapixels."]);
}

$nomeArquivo = bin2hex(random_bytes(16)) . "." . $tipos[$mime];
$fp = null;
try {
    if (limite_excedido($pdo, "pet", 5, 60)) {
        responder(429, ["sucesso" => false, "mensagem" => "Você enviou muitos registros em pouco tempo. Tente novamente mais tarde."]);
    }
    registrar_tentativa($pdo, "pet");

    $fp = fopen("php://temp", "r+");
fwrite($fp, $imagemSegura);
rewind($fp);
    if ($fp === false) {
        throw new RuntimeException("Não foi possível ler a foto enviada.");
    }
    $stmt = $pdo->prepare("INSERT INTO registros_pets (nome_responsavel, email, descricao, localizacao, foto_arquivo, foto_tipo, foto_dados, status, latitude, longitude, usuario_id, nome_animal, especie, idade) VALUES (:nome, :email, :descricao, :localizacao, :foto, :tipo, :dados, :status, :lat, :lng, :usuario, :nome_animal, :especie, :idade)");
    $stmt->bindValue(":nome", $nome);
    $stmt->bindValue(":email", $email);
    $stmt->bindValue(":descricao", $descricao);
    $stmt->bindValue(":localizacao", $localizacao);
    $stmt->bindValue(":foto", $nomeArquivo);
    $stmt->bindValue(":tipo", $mime);
    $stmt->bindValue(":status", $statusInicial);
    $stmt->bindValue(":lat", $latitude, $latitude === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":lng", $longitude, $longitude === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":usuario", (int)$usuarioLogado["id"], PDO::PARAM_INT);
    $stmt->bindValue(":nome_animal", $nomeAnimal, $nomeAnimal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(":especie", $especie);
    $stmt->bindValue(":idade", $idade);
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
