<?php
// Envio de e-mail pela API do Resend (https://resend.com).
// Precisa das variáveis RESEND_API_KEY e MAIL_FROM. Sem elas, nada é enviado.

function email_configurado(): bool
{
    return getenv('RESEND_API_KEY') && getenv('MAIL_FROM') && function_exists('curl_init');
}

function enviar_email(string $para, string $assunto, string $html): bool
{
    if (!email_configurado()) {
        return false;
    }
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getenv('RESEND_API_KEY'),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => getenv('MAIL_FROM'),
            'to' => [$para],
            'subject' => $assunto,
            'html' => $html
        ])
    ]);
    $resposta = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($codigo < 200 || $codigo >= 300) {
        error_log('Falha ao enviar e-mail (HTTP ' . $codigo . '): ' . (string)$resposta);
        return false;
    }
    return true;
}

function url_base(): string
{
    $fixa = getenv('APP_URL');
    if ($fixa) {
        return rtrim($fixa, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
