<?php
// Proteção contra spam, força bruta e excesso de tentativas.
require_once __DIR__ . '/auth.php';

// IP real do visitante. Cada proxy acrescenta o IP de quem se conectou à direita do
// X-Forwarded-For; por isso pegamos a posição (PROXY_HOPS) contada a partir da direita.
// Os itens da esquerda podem ter sido inventados pelo visitante, e por isso são ignorados.
function ip_cliente(): string
{
    if (cloudflare_ativo()) {
        $cf = trim($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
    }
    $hops = numero_proxies();
    if ($hops > 0) {
        $partes = array_values(array_filter(
            array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')),
            fn($p) => $p !== ''
        ));
        $indice = count($partes) - $hops;
        if ($indice >= 0 && filter_var($partes[$indice], FILTER_VALIDATE_IP)) {
            return $partes[$indice];
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function chave_hash(string $valor): string
{
    return hash('sha256', $valor . (getenv('APP_SALT') ?: 'conectando-patas'));
}

function ip_hash(): string
{
    return chave_hash(ip_cliente());
}

// Campo escondido "website": pessoas não preenchem, robôs costumam preencher.
function honeypot_preenchido(): bool
{
    return trim($_POST['website'] ?? '') !== '';
}

// Sem $chave o limite é contado por IP; com $chave (ex.: e-mail) é contado por essa chave.
function limite_excedido(PDO $pdo, string $acao, int $maximo, int $minutos, ?string $chave = null): bool
{
    $minutos = max(1, $minutos);
    $hash = $chave === null ? ip_hash() : chave_hash(mb_strtolower($chave));
    $pdo->exec("DELETE FROM limite_envios WHERE criado_em < NOW() - INTERVAL '1 day'");
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM limite_envios
         WHERE acao = :acao AND ip_hash = :hash
           AND criado_em > NOW() - INTERVAL '{$minutos} minutes'"
    );
    $stmt->execute([':acao' => $acao, ':hash' => $hash]);
    return (int)$stmt->fetchColumn() >= $maximo;
}

// Após um login bem-sucedido, zera a contagem de erros da conta.
function limpar_tentativas(PDO $pdo, string $acao, string $chave): void
{
    $stmt = $pdo->prepare('DELETE FROM limite_envios WHERE acao = :acao AND ip_hash = :hash');
    $stmt->execute([':acao' => $acao, ':hash' => chave_hash(mb_strtolower($chave))]);
}

function registrar_tentativa(PDO $pdo, string $acao, ?string $chave = null): void
{
    $hash = $chave === null ? ip_hash() : chave_hash(mb_strtolower($chave));
    $stmt = $pdo->prepare('INSERT INTO limite_envios (acao, ip_hash) VALUES (:acao, :hash)');
    $stmt->execute([':acao' => $acao, ':hash' => $hash]);
}
