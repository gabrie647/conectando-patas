<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

require_once __DIR__ . '/conexao.php';

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Informe um e-mail válido e a senha.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, nome, sobrenome, email, senha FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        http_response_code(401);
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.']);
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
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
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao verificar o login. Tente novamente.']);
}
