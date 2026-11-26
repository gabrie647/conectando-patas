<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/protecao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}
csrf_exigir();
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/email.php';

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150 || $senha === '' || strlen($senha) > 1024) {
    responder(400, ['sucesso' => false, 'mensagem' => 'Informe um e-mail válido e a senha.']);
}

try {
    // Dois limites: por IP (contra ataques de um endereço) e por conta (contra ataques distribuídos).
    if (limite_excedido($pdo, 'login_falha', 10, 15) || limite_excedido($pdo, 'login_conta', 5, 15, $email)) {
        responder(429, ['sucesso' => false, 'mensagem' => 'Muitas tentativas erradas. Aguarde alguns minutos e tente de novo.']);
    }

    $stmt = $pdo->prepare('SELECT id, nome, sobrenome, email, senha, email_confirmado FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    // Confere a senha mesmo quando a conta não existe, para o tempo de resposta não revelar contas.
    $hashReferencia = $usuario['senha'] ?? password_hash('referencia-sem-conta', PASSWORD_DEFAULT);
    $senhaCorreta = password_verify($senha, $hashReferencia);

    if (!$usuario || !$senhaCorreta) {
        registrar_tentativa($pdo, 'login_falha');
        registrar_tentativa($pdo, 'login_conta', $email);
        responder(401, ['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.']);
    }

    if (!db_bool($usuario['email_confirmado'])) {
        // Senha correta, mas conta não confirmada: reenvia o link (no máximo 1 a cada 10 minutos por conta).
        if (email_configurado() && !limite_excedido($pdo, 'reenvio_confirmacao', 1, 10, $email)) {
            registrar_tentativa($pdo, 'reenvio_confirmacao', $email);
            $token = gerar_token_confirmacao($pdo, (int)$usuario['id']);
            if (enviar_confirmacao_email($usuario['nome'], $usuario['email'], $token)) {
                responder(403, ['sucesso' => false, 'mensagem' => 'Sua conta ainda não foi confirmada. Enviamos um novo link para o seu e-mail (verifique também o spam).']);
            }
        }
        responder(403, ['sucesso' => false, 'mensagem' => 'Confirme seu e-mail antes de entrar. Verifique a sua caixa de entrada (e o spam).']);
    }

    limpar_tentativas($pdo, 'login_conta', $email);

    // Já existe sessão criada pelo csrf_exigir(); trocamos o ID para evitar fixação de sessão.
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int)$usuario['id'],
        'nome' => $usuario['nome'],
        'sobrenome' => $usuario['sobrenome'],
        'email' => $usuario['email']
    ];

    echo json_encode(['sucesso' => true, 'mensagem' => 'Login realizado com sucesso.', 'usuario' => $_SESSION['usuario']]);
} catch (Throwable $e) {
    error_log('Erro no login: ' . $e->getMessage());
    responder(500, ['sucesso' => false, 'mensagem' => 'Erro ao verificar o login. Tente novamente.']);
}
