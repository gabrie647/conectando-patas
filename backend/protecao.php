<?php
// Proteção simples contra spam e excesso de tentativas.
require_once __DIR__ . '/auth.php';

function ip_cliente(): string
{
    $encaminhado = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($encaminhado !== '') {
        return trim(explode(',', $encaminhado)[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function ip_hash(): string
{
    return hash('sha256', ip_cliente() . (getenv('APP_SALT') ?: 'conectando-patas'));
}

// Campo escondido "website": pessoas não preenchem, robôs costumam preencher.
function honeypot_preenchido(): bool
{
    return trim($_POST['website'] ?? '') !== '';
}

function limite_excedido(PDO $pdo, string $acao, int $maximo, int $minutos): bool
{
    $minutos = max(1, $minutos);
    $pdo->exec("DELETE FROM limite_envios WHERE criado_em < NOW() - INTERVAL '1 day'");
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM limite_envios
         WHERE acao = :acao AND ip_hash = :hash
           AND criado_em > NOW() - INTERVAL '{$minutos} minutes'"
    );
    $stmt->execute([':acao' => $acao, ':hash' => ip_hash()]);
    return (int)$stmt->fetchColumn() >= $maximo;
}

function registrar_tentativa(PDO $pdo, string $acao): void
{
    $stmt = $pdo->prepare('INSERT INTO limite_envios (acao, ip_hash) VALUES (:acao, :hash)');
    $stmt->execute([':acao' => $acao, ':hash' => ip_hash()]);
}
