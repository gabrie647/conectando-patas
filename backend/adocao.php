<?php
// Fluxo de adoção: solicitar, listar, decidir e cancelar.
// Decide o responsável pelo animal (quem cadastrou, com conta) ou um administrador.
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
require_once __DIR__ . "/protecao.php";
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/email.php";

$usuario = exigir_login();
$ehAdmin = eh_admin($usuario);

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo !== "GET" && $metodo !== "POST") {
    responder(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}
if ($metodo === "POST") {
    csrf_exigir();
}
$acao = $metodo === "POST" ? ($_POST["acao"] ?? "") : ($_GET["acao"] ?? "");

function texto_curto(string $texto, int $max): string
{
    return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max - 1) . "…" : $texto;
}

function aviso_email(string $para, string $assunto, string $titulo, string $texto): void
{
    $html = "<h2>" . htmlspecialchars($titulo, ENT_QUOTES, "UTF-8") . "</h2>"
          . "<p>" . htmlspecialchars($texto, ENT_QUOTES, "UTF-8") . "</p>"
          . "<p>Acesse <strong>Minha conta</strong> no Conectando Patas para ver os detalhes.</p>";
    enviar_email($para, $assunto, $html);
}

function emails_admin(): array
{
    return array_filter(array_map(
        fn($e) => strtolower(trim($e)),
        explode(",", getenv("ADMIN_EMAILS") ?: "")
    ));
}

// Contato do responsável: a conta de quem cadastrou o animal; para cadastros antigos, o e-mail informado.
function contato_responsavel(PDO $pdo, array $pet): array
{
    if (!empty($pet["usuario_id"])) {
        $stmt = $pdo->prepare("SELECT nome, sobrenome, email FROM usuarios WHERE id = :id");
        $stmt->execute([":id" => (int)$pet["usuario_id"]]);
        $dono = $stmt->fetch();
        if ($dono) {
            return ["nome" => trim($dono["nome"] . " " . $dono["sobrenome"]), "email" => $dono["email"]];
        }
    }
    return ["nome" => $pet["nome_responsavel"], "email" => $pet["email"]];
}

try {

    // ---------- Solicitar adoção (usuário logado)
    if ($acao === "solicitar") {
        $idAnimal = filter_var($_POST["animal"] ?? null, FILTER_VALIDATE_INT);
        $mensagem = trim($_POST["mensagem"] ?? "");

        if (!$idAnimal || $idAnimal < 1) {
            responder(400, ["sucesso" => false, "mensagem" => "Animal inválido."]);
        }
        if (mb_strlen($mensagem) < 10 || mb_strlen($mensagem) > 1000) {
            responder(400, ["sucesso" => false, "mensagem" => "A mensagem precisa ter entre 10 e 1000 caracteres."]);
        }
        if (limite_excedido($pdo, "solicitacao", 10, 60, $usuario["email"])) {
            responder(429, ["sucesso" => false, "mensagem" => "Muitas solicitações em pouco tempo. Tente novamente mais tarde."]);
        }

        $stmt = $pdo->prepare("SELECT id, descricao, status, usuario_id, nome_responsavel, email FROM registros_pets WHERE id = :id");
        $stmt->execute([":id" => $idAnimal]);
        $pet = $stmt->fetch();
        if (!$pet || $pet["status"] !== "aprovado") {
            responder(404, ["sucesso" => false, "mensagem" => "Este animal não está disponível para adoção."]);
        }
        if (!empty($pet["usuario_id"]) && (int)$pet["usuario_id"] === (int)$usuario["id"]) {
            responder(400, ["sucesso" => false, "mensagem" => "Você não pode pedir a adoção de um animal que você mesmo cadastrou."]);
        }

        registrar_tentativa($pdo, "solicitacao", $usuario["email"]);
        try {
            $stmt = $pdo->prepare("INSERT INTO solicitacoes_adocao (registro_pet_id, usuario_id, mensagem) VALUES (:pet, :usuario, :mensagem)");
            $stmt->execute([":pet" => $idAnimal, ":usuario" => (int)$usuario["id"], ":mensagem" => $mensagem]);
        } catch (PDOException $e) {
            if ($e->getCode() === "23505") {
                responder(409, ["sucesso" => false, "mensagem" => "Você já tem uma solicitação em análise para este animal."]);
            }
            throw $e;
        }

        $contato = contato_responsavel($pdo, $pet);
        $titulo = texto_curto($pet["descricao"], 80);
        aviso_email($contato["email"], "Nova solicitação de adoção - Conectando Patas",
            "Alguém quer adotar um animal seu",
            "Uma pessoa pediu a adoção de: " . $titulo . ". Entre em Minha conta para ver a mensagem e decidir.");
        foreach (emails_admin() as $adm) {
            if ($adm !== strtolower($contato["email"])) {
                aviso_email($adm, "Nova solicitação de adoção - Conectando Patas",
                    "Nova solicitação de adoção",
                    "Uma pessoa pediu a adoção de: " . $titulo . ". Acompanhe em Minha conta.");
            }
        }

        responder(200, ["sucesso" => true, "mensagem" => "Solicitação enviada! Avisaremos quando houver uma resposta."]);
    }

    // ---------- Minhas solicitações (quem pediu)
    if ($acao === "minhas") {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.status, s.mensagem, s.criado_em, s.decidido_em,
                    p.id AS animal_id, p.descricao, p.usuario_id AS dono_id,
                    p.nome_responsavel, p.email AS email_responsavel
             FROM solicitacoes_adocao s
             JOIN registros_pets p ON p.id = s.registro_pet_id
             WHERE s.usuario_id = :usuario
             ORDER BY s.criado_em DESC LIMIT 50"
        );
        $stmt->execute([":usuario" => (int)$usuario["id"]]);

        $itens = [];
        foreach ($stmt->fetchAll() as $linha) {
            $item = [
                "id" => (int)$linha["id"],
                "status" => $linha["status"],
                "mensagem" => $linha["mensagem"],
                "criado_em" => $linha["criado_em"],
                "decidido_em" => $linha["decidido_em"],
                "animal_id" => (int)$linha["animal_id"],
                "descricao" => $linha["descricao"],
                "contato" => null
            ];
            // Só depois da aprovação o interessado recebe o contato do responsável.
            if ($linha["status"] === "aprovada") {
                $item["contato"] = contato_responsavel($pdo, [
                    "usuario_id" => $linha["dono_id"],
                    "nome_responsavel" => $linha["nome_responsavel"],
                    "email" => $linha["email_responsavel"]
                ]);
            }
            $itens[] = $item;
        }
        responder(200, ["sucesso" => true, "itens" => $itens]);
    }

    // ---------- Solicitações recebidas (responsável vê as dos seus animais; administrador vê todas)
    if ($acao === "recebidas") {
        $sql = "SELECT s.id, s.status, s.mensagem, s.criado_em, s.decidido_em,
                       p.id AS animal_id, p.descricao,
                       u.nome AS solicitante_nome, u.sobrenome AS solicitante_sobrenome, u.email AS solicitante_email
                FROM solicitacoes_adocao s
                JOIN registros_pets p ON p.id = s.registro_pet_id
                JOIN usuarios u ON u.id = s.usuario_id";
        $params = [];
        if (!$ehAdmin) {
            $sql .= " WHERE p.usuario_id = :usuario";
            $params[":usuario"] = (int)$usuario["id"];
        }
        $sql .= " ORDER BY (s.status = 'pendente') DESC, s.criado_em DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $itens = array_map(fn($l) => [
            "id" => (int)$l["id"],
            "status" => $l["status"],
            "mensagem" => $l["mensagem"],
            "criado_em" => $l["criado_em"],
            "decidido_em" => $l["decidido_em"],
            "animal_id" => (int)$l["animal_id"],
            "descricao" => $l["descricao"],
            "solicitante" => [
                "nome" => trim($l["solicitante_nome"] . " " . $l["solicitante_sobrenome"]),
                "email" => $l["solicitante_email"]
            ]
        ], $stmt->fetchAll());

        responder(200, ["sucesso" => true, "itens" => $itens]);
    }

    // ---------- Aprovar ou recusar (responsável do animal ou administrador)
    if ($acao === "decidir") {
        $idSolicitacao = filter_var($_POST["solicitacao"] ?? null, FILTER_VALIDATE_INT);
        $decisao = $_POST["decisao"] ?? "";
        if (!$idSolicitacao || !in_array($decisao, ["aprovar", "recusar"], true)) {
            responder(400, ["sucesso" => false, "mensagem" => "Pedido inválido."]);
        }
        $aprovar = $decisao === "aprovar";

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT s.id, s.status, s.registro_pet_id,
                    p.status AS pet_status, p.usuario_id AS dono_id, p.descricao,
                    p.nome_responsavel, p.email AS email_responsavel,
                    u.email AS solicitante_email
             FROM solicitacoes_adocao s
             JOIN registros_pets p ON p.id = s.registro_pet_id
             JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.id = :id
             FOR UPDATE OF s"
        );
        $stmt->execute([":id" => $idSolicitacao]);
        $sol = $stmt->fetch();

        if (!$sol) {
            $pdo->rollBack();
            responder(404, ["sucesso" => false, "mensagem" => "Solicitação não encontrada."]);
        }
        $ehDono = !empty($sol["dono_id"]) && (int)$sol["dono_id"] === (int)$usuario["id"];
        if (!$ehAdmin && !$ehDono) {
            $pdo->rollBack();
            responder(403, ["sucesso" => false, "mensagem" => "Só o responsável pelo animal ou um administrador pode decidir."]);
        }
        if ($sol["status"] !== "pendente") {
            $pdo->rollBack();
            responder(409, ["sucesso" => false, "mensagem" => "Esta solicitação já foi respondida."]);
        }
        if ($sol["pet_status"] !== "aprovado") {
            $pdo->rollBack();
            responder(409, ["sucesso" => false, "mensagem" => "Este animal não está mais disponível."]);
        }

        $petId = (int)$sol["registro_pet_id"];
        $por = $usuario["email"];

        $pdo->prepare("UPDATE solicitacoes_adocao SET status = :status, decidido_por = :por, decidido_em = NOW() WHERE id = :id")
            ->execute([":status" => $aprovar ? "aprovada" : "recusada", ":por" => $por, ":id" => $idSolicitacao]);

        if ($aprovar) {
            $pdo->prepare("UPDATE registros_pets SET status = 'adotado' WHERE id = :pet")
                ->execute([":pet" => $petId]);
            // As outras solicitações pendentes deste animal são encerradas.
            $pdo->prepare("UPDATE solicitacoes_adocao SET status = 'encerrada', decidido_por = :por, decidido_em = NOW()
                           WHERE registro_pet_id = :pet AND status = 'pendente'")
                ->execute([":por" => $por, ":pet" => $petId]);
        }
        $pdo->commit();

        $titulo = texto_curto($sol["descricao"], 80);
        if ($aprovar) {
            $responsavel = contato_responsavel($pdo, [
                "usuario_id" => $sol["dono_id"],
                "nome_responsavel" => $sol["nome_responsavel"],
                "email" => $sol["email_responsavel"]
            ]);
            aviso_email($sol["solicitante_email"], "Sua solicitação de adoção foi aprovada - Conectando Patas",
                "Boa notícia!",
                "Sua solicitação para adotar \"" . $titulo . "\" foi aprovada. Entre em contato com o responsável: "
                . $responsavel["nome"] . " (" . $responsavel["email"] . ").");
            responder(200, ["sucesso" => true, "mensagem" => "Adoção aprovada. O animal foi marcado como adotado."]);
        }

        aviso_email($sol["solicitante_email"], "Atualização sobre sua solicitação de adoção - Conectando Patas",
            "Sua solicitação não foi aceita",
            "A solicitação para adotar \"" . $titulo . "\" não foi aceita desta vez. Você pode procurar outros animais disponíveis.");
        responder(200, ["sucesso" => true, "mensagem" => "Solicitação recusada."]);
    }

    // ---------- Cancelar (quem pediu, enquanto estiver em análise)
    if ($acao === "cancelar") {
        $idSolicitacao = filter_var($_POST["solicitacao"] ?? null, FILTER_VALIDATE_INT);
        if (!$idSolicitacao) {
            responder(400, ["sucesso" => false, "mensagem" => "Pedido inválido."]);
        }
        $stmt = $pdo->prepare("UPDATE solicitacoes_adocao SET status = 'cancelada', decidido_por = :por, decidido_em = NOW()
                               WHERE id = :id AND usuario_id = :usuario AND status = 'pendente'");
        $stmt->execute([":por" => $usuario["email"], ":id" => $idSolicitacao, ":usuario" => (int)$usuario["id"]]);
        if ($stmt->rowCount() === 0) {
            responder(404, ["sucesso" => false, "mensagem" => "Solicitação não encontrada ou já respondida."]);
        }
        responder(200, ["sucesso" => true, "mensagem" => "Solicitação cancelada."]);
    }

    responder(400, ["sucesso" => false, "mensagem" => "Ação desconhecida."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro no fluxo de adoção: " . $e->getMessage());
    responder(500, ["sucesso" => false, "mensagem" => "Não foi possível concluir a ação agora."]);
}
