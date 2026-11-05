<?php
// Funções de sessão e permissão usadas por vários endpoints.

function responder(int $codigo, array $dados): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($dados);
    exit;
}

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function usuario_atual(): ?array
{
    iniciar_sessao();
    return $_SESSION['usuario'] ?? null;
}

// Administradores são definidos pela variável ADMIN_EMAILS (e-mails separados por vírgula).
function eh_admin(?array $usuario): bool
{
    if (!$usuario || empty($usuario['email'])) {
        return false;
    }
    $lista = array_filter(array_map(
        fn($e) => strtolower(trim($e)),
        explode(',', getenv('ADMIN_EMAILS') ?: '')
    ));
    return in_array(strtolower($usuario['email']), $lista, true);
}

function exigir_admin(): array
{
    $usuario = usuario_atual();
    if (!$usuario) {
        responder(401, ['sucesso' => false, 'mensagem' => 'Faça login para acessar o painel.']);
    }
    if (!eh_admin($usuario)) {
        responder(403, ['sucesso' => false, 'mensagem' => 'Esta conta não tem permissão de administrador.']);
    }
    return $usuario;
}
