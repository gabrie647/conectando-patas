<?php
// Conta do usuário logado.
//   GET  /backend/conta.php                      -> dados do perfil + registros de animais da pessoa
//   POST acao=perfil  (nome, sobrenome, cidade, bairro, numero)
//   POST acao=senha   (senha_atual, nova_senha, confirmar_senha)
//   POST acao=excluir (senha_atual)
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/auth.php';

$sessao = usuario_atual();
if (!$sessao) {
    responder(401, ['sucesso' => false, 'mensagem' => 'Faça login para acessar sua conta.']);
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/protecao.php';

$idUsuario = (int)$sessao['id'];

function encerrar_sessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Confere a senha atual com limite de tentativas erradas (5 em 15 minutos por IP).
function conferir_senha_atual(PDO $pdo, int $idUsuario, string $senha): void
{
    if (limite_excedido($pdo, 'conta_senha_falha', 5, 15)) {
        responder(429, ['sucesso' => false, 'mensagem' => 'Muitas tentativas erradas. Aguarde alguns minutos e tente de novo.']);
    }
    $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $idUsuario]);
    $hash = $stmt->fetchColumn();
    if ($senha === '' || !$hash || !password_verify($senha, $hash)) {
        registrar_tentativa($pdo, 'conta_senha_falha');
        responder(403, ['sucesso' => false, 'mensagem' => 'Senha atual incorreta.']);
    }
}

try {
    // ---------- Leitura ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT id, nome, sobrenome, email, cidade, bairro, numero, criado_em FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $idUsuario]);
        $u = $stmt->fetch();
        if (!$u) {
            encerrar_sessao();
            responder(401, ['sucesso' => false, 'mensagem' => 'Conta não encontrada. Faça login novamente.']);
        }

        $stmt = $pdo->prepare(
            'SELECT id, descricao, localizacao, status, criado_em
               FROM registros_pets
              WHERE LOWER(email) = LOWER(:email)
              ORDER BY criado_em DESC, id DESC
              LIMIT 50'
        );
        $stmt->execute([':email' => $u['email']]);
        $pets = array_map(fn($p) => [
            'id' => (int)$p['id'],
            'descricao' => $p['descricao'],
            'localizacao' => $p['localizacao'],
            'status' => $p['status'],
            'criado_em' => $p['criado_em'],
            // A foto só é pública depois de aprovada.
            'foto' => $p['status'] === 'aprovado' ? '/backend/foto.php?id=' . (int)$p['id'] : null
        ], $stmt->fetchAll());

        echo json_encode([
            'sucesso' => true,
            'usuario' => [
                'nome' => $u['nome'],
                'sobrenome' => $u['sobrenome'],
                'email' => $u['email'],
                'cidade' => $u['cidade'],
                'bairro' => $u['bairro'],
                'numero' => $u['numero'],
                'criado_em' => $u['criado_em']
            ],
            'pets' => $pets,
            'admin' => eh_admin($sessao)
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }

    $acao = $_POST['acao'] ?? '';

    // ---------- Atualizar dados pessoais ----------
    if ($acao === 'perfil') {
        $nome = trim($_POST['nome'] ?? '');
        $sobrenome = trim($_POST['sobrenome'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $numero = trim($_POST['numero'] ?? '');

        if ($nome === '' || $sobrenome === '' || $cidade === '' || $bairro === '' || $numero === '') {
            responder(400, ['sucesso' => false, 'mensagem' => 'Preencha todos os campos.']);
        }
        if (mb_strlen($nome) > 100 || mb_strlen($sobrenome) > 100 || mb_strlen($cidade) > 100
            || mb_strlen($bairro) > 100 || mb_strlen($numero) > 20) {
            responder(400, ['sucesso' => false, 'mensagem' => 'Algum campo excedeu o tamanho permitido.']);
        }

        $stmt = $pdo->prepare(
            'UPDATE usuarios SET nome = :nome, sobrenome = :sobrenome, cidade = :cidade, bairro = :bairro, numero = :numero WHERE id = :id'
        );
        $stmt->execute([
            ':nome' => $nome, ':sobrenome' => $sobrenome, ':cidade' => $cidade,
            ':bairro' => $bairro, ':numero' => $numero, ':id' => $idUsuario
        ]);

        $_SESSION['usuario']['nome'] = $nome;
        $_SESSION['usuario']['sobrenome'] = $sobrenome;
        echo json_encode(['sucesso' => true, 'mensagem' => 'Dados atualizados com sucesso.']);
        exit;
    }

    // ---------- Alterar senha ----------
    if ($acao === 'senha') {
        $atual = $_POST['senha_atual'] ?? '';
        $nova = $_POST['nova_senha'] ?? '';
        $confirmar = $_POST['confirmar_senha'] ?? '';

        if ($nova === '' || $confirmar === '') {
            responder(400, ['sucesso' => false, 'mensagem' => 'Preencha a nova senha e a confirmação.']);
        }
        if (strlen($nova) < 8) {
            responder(400, ['sucesso' => false, 'mensagem' => 'A nova senha precisa ter pelo menos 8 caracteres.']);
        }
        if ($nova !== $confirmar) {
            responder(400, ['sucesso' => false, 'mensagem' => 'As senhas não são iguais.']);
        }
        if ($nova === $atual) {
            responder(400, ['sucesso' => false, 'mensagem' => 'A nova senha precisa ser diferente da atual.']);
        }

        conferir_senha_atual($pdo, $idUsuario, $atual);

        $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
        $stmt->execute([':senha' => password_hash($nova, PASSWORD_DEFAULT), ':id' => $idUsuario]);

        // Invalida links de recuperação antigos e renova o identificador da sessão.
        $pdo->prepare('UPDATE redefinicoes_senha SET usado = TRUE WHERE usuario_id = :id')->execute([':id' => $idUsuario]);
        session_regenerate_id(true);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso.']);
        exit;
    }

    // ---------- Excluir conta ----------
    if ($acao === 'excluir') {
        conferir_senha_atual($pdo, $idUsuario, $_POST['senha_atual'] ?? '');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $idUsuario]);
        $email = $stmt->fetchColumn();
        if ($email) {
            $pdo->prepare('DELETE FROM registros_pets WHERE LOWER(email) = LOWER(:email)')->execute([':email' => $email]);
            $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $idUsuario]);
        }
        $pdo->commit();

        encerrar_sessao();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Sua conta foi excluída.']);
        exit;
    }

    responder(400, ['sucesso' => false, 'mensagem' => 'Ação inválida.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro na conta do usuário: ' . $e->getMessage());
    responder(500, ['sucesso' => false, 'mensagem' => 'Não foi possível concluir a operação. Tente novamente.']);
}
