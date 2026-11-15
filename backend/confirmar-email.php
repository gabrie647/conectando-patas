<?php
// Link enviado por e-mail no cadastro. Ativa a conta e volta para a tela de login.
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/email.php";
header("Cache-Control: no-store");

$base = rtrim(url_base(), "/");
$token = $_GET["token"] ?? "";

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    header("Location: " . $base . "/login/index.html?erro=1");
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, usuario_id FROM confirmacoes_email
         WHERE token_hash = :hash AND usado = FALSE AND expira_em > NOW()
         LIMIT 1"
    );
    $stmt->execute([":hash" => hash("sha256", $token)]);
    $confirmacao = $stmt->fetch();

    if (!$confirmacao) {
        header("Location: " . $base . "/login/index.html?erro=1");
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE usuarios SET email_confirmado = TRUE WHERE id = :id")
        ->execute([":id" => $confirmacao["usuario_id"]]);
    $pdo->prepare("UPDATE confirmacoes_email SET usado = TRUE WHERE usuario_id = :id")
        ->execute([":id" => $confirmacao["usuario_id"]]);
    $pdo->commit();

    header("Location: " . $base . "/login/index.html?confirmado=1");
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao confirmar e-mail: " . $e->getMessage());
    header("Location: " . $base . "/login/index.html?erro=1");
    exit;
}
