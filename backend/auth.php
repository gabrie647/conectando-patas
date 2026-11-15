<?php
// Funções de sessão, permissão e segurança usadas pelos endpoints.

const SESSAO_OCIOSA_SEGUNDOS = 7200; // 2 horas sem atividade encerram a sessão

function responder(int $codigo, array $dados): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($dados);
    exit;
}

// Quantos proxies/balanceadores ficam na frente do site (ex.: Render = 1). 0 = acesso direto.
// Com PROXY_HOPS = 0 os cabeçalhos X-Forwarded-* são ignorados, porque qualquer um pode forjá-los.
function numero_proxies(): int
{
    return max(0, (int)(getenv('PROXY_HOPS') ?: 0));
}

// Cloudflare na frente do site: define CF-Connecting-IP e X-Forwarded-Proto.
// Só ative com CLOUDFLARE=1 e acesse o site somente pelo domínio do Cloudflare.
function cloudflare_ativo(): bool
{
    return getenv('CLOUDFLARE') === '1';
}

function proxy_confiavel(): bool
{
    return numero_proxies() > 0 || cloudflare_ativo();
}

function https_ativo(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!proxy_confiavel()) {
        return false;
    }
    // O último valor do X-Forwarded-Proto é o que o proxy mais próximo registrou.
    $protos = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return strtolower((string)end($protos)) === 'https';
}

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)SESSAO_OCIOSA_SEGUNDOS);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => https_ativo(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();

    // Sessão ociosa por tempo demais é encerrada.
    $agora = time();
    if (isset($_SESSION['ultimo_acesso']) && ($agora - (int)$_SESSION['ultimo_acesso']) > SESSAO_OCIOSA_SEGUNDOS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultimo_acesso'] = $agora;
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

function encerrar_sessao(): void
{
    iniciar_sessao();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'] ?? '',
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_destroy();
}

// Token CSRF: ligado à sessão e enviado pelo JavaScript no cabeçalho X-CSRF-Token.
function csrf_token(): string
{
    iniciar_sessao();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_exigir(): void
{
    iniciar_sessao();
    $esperado = $_SESSION['csrf'] ?? '';
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($esperado === '' || $enviado === '' || !hash_equals($esperado, $enviado)) {
        responder(419, ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.']);
    }
}

// Política de senha: mínimo de 10 caracteres, letras e números, limite de 72 bytes (limite do bcrypt).
function validar_senha(string $senha): ?string
{
    if (mb_strlen($senha) < 10) {
        return 'A senha precisa ter pelo menos 10 caracteres.';
    }
    if (strlen($senha) > 72) {
        return 'A senha pode ter no máximo 72 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
        return 'A senha precisa ter letras e números.';
    }
    $comuns = ['senha1234', 'senhaforte1', 'qwerty1234', 'password12', 'conectando1234', 'abcdefgh12'];
    if (in_array(mb_strtolower($senha), $comuns, true)) {
        return 'Escolha uma senha menos comum.';
    }
    return null;
}

// Exige usuário logado: responde 401 se não houver sessão. Usado pelas APIs de páginas restritas.
function exigir_login(): array
{
    $usuario = usuario_atual();
    if (!$usuario) {
        responder(401, ['sucesso' => false, 'mensagem' => 'Faça login para continuar.']);
    }
    return $usuario;
}

// PDO/PostgreSQL pode devolver booleanos como true, 't' ou '1'.
function db_bool($valor): bool
{
    return $valor === true || $valor === 't' || $valor === '1' || $valor === 1;
}
