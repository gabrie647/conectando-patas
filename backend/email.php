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

// Cria um novo link de confirmação e invalida os anteriores da mesma conta.
function gerar_token_confirmacao(PDO $pdo, int $idUsuario): string
{
    $pdo->prepare("UPDATE confirmacoes_email SET usado = TRUE WHERE usuario_id = :id AND usado = FALSE")
        ->execute([":id" => $idUsuario]);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        "INSERT INTO confirmacoes_email (usuario_id, token_hash, expira_em)
         VALUES (:id, :hash, NOW() + INTERVAL '24 hours')"
    )->execute([":id" => $idUsuario, ":hash" => hash("sha256", $token)]);
    return $token;
}

// Envia o e-mail com o link de confirmação. Retorna false se o envio falhar.
function enviar_confirmacao_email(string $nome, string $para, string $token): bool
{
    $link = rtrim(url_base(), "/") . "/backend/confirmar-email.php?token=" . $token;
    $html = "<p>Olá, " . htmlspecialchars($nome, ENT_QUOTES, "UTF-8") . "!</p>"
          . "<p>Para ativar sua conta no Conectando Patas, confirme seu e-mail:</p>"
          . "<p><a href=\"" . $link . "\">Confirmar meu e-mail</a></p>"
          . "<p>O link vale por 24 horas. Se você não fez este cadastro, ignore esta mensagem.</p>";
    return enviar_email($para, "Confirme seu e-mail - Conectando Patas", $html);
}
