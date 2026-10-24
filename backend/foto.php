<?php
// Entrega a foto de um pet guardada no banco: /backend/foto.php?id=123
// Fotos de pets ainda não aprovados só aparecem para o administrador.
require_once __DIR__ . "/auth.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit;
}

require_once __DIR__ . "/conexao.php";

try {
    $stmt = $pdo->prepare("SELECT foto_tipo, foto_dados, status FROM registros_pets WHERE id = :id AND foto_dados IS NOT NULL");
    $stmt->execute([":id" => $id]);
    $stmt->bindColumn(1, $tipo);
    $stmt->bindColumn(2, $dados, PDO::PARAM_LOB);
    $stmt->bindColumn(3, $status);

    if (!$stmt->fetch(PDO::FETCH_BOUND)) {
        http_response_code(404);
        exit;
    }

    $publica = $status === "aprovado";
    if (!$publica && !eh_admin(usuario_atual())) {
        http_response_code(404);
        exit;
    }

    $permitidos = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    if (!in_array($tipo, $permitidos, true)) {
        http_response_code(404);
        exit;
    }

    header("Content-Type: " . $tipo);
    header("X-Content-Type-Options: nosniff");
    header($publica ? "Cache-Control: public, max-age=86400" : "Cache-Control: private, no-store");
    if (is_resource($dados)) {
        fpassthru($dados);
    } else {
        echo $dados;
    }
} catch (Throwable $e) {
    error_log("Erro ao entregar foto: " . $e->getMessage());
    http_response_code(500);
}
