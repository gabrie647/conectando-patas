<?php
// Verifica as variáveis de ambiente. Nunca exibe valores, só os nomes que faltam.

function configuracao_pendente(): array
{
    $obrigatorias = ['DATABASE_URL'];
    $recomendadas = ['APP_URL', 'APP_SALT', 'ADMIN_EMAILS', 'PROXY_HOPS', 'RESEND_API_KEY', 'MAIL_FROM'];

    $faltando = [];
    foreach (array_merge($obrigatorias, $recomendadas) as $nome) {
        if (getenv($nome) === false || trim((string)getenv($nome)) === '') {
            $faltando[] = $nome;
        }
    }
    return $faltando;
}

function ambiente_producao(): bool
{
    return strtolower((string)getenv('APP_ENV')) === 'production';
}
