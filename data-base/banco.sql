CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    sobrenome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha TEXT NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    bairro VARCHAR(100) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    termos_aceitos BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS registros_pets (
    id BIGSERIAL PRIMARY KEY,
    nome_responsavel VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    localizacao VARCHAR(255) NOT NULL,
    foto_arquivo VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_registros_pets_email ON registros_pets(email);
CREATE INDEX IF NOT EXISTS idx_registros_pets_criado_em ON registros_pets(criado_em);


CREATE TABLE IF NOT EXISTS contatos (
    id BIGSERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    assunto VARCHAR(30) NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_contatos_criado_em ON contatos(criado_em);

ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS foto_tipo VARCHAR(50);
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS foto_dados BYTEA;


-- Moderação: pets só aparecem no site depois de aprovados no painel
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'aprovado';
ALTER TABLE registros_pets ALTER COLUMN status SET DEFAULT 'aprovado';
CREATE INDEX IF NOT EXISTS idx_registros_pets_status ON registros_pets(status);

-- Painel: marcar mensagens de contato como lidas
ALTER TABLE contatos ADD COLUMN IF NOT EXISTS lida BOOLEAN NOT NULL DEFAULT FALSE;

-- Proteção contra spam / tentativas em excesso (guarda só um hash do IP)
CREATE TABLE IF NOT EXISTS limite_envios (
    id BIGSERIAL PRIMARY KEY,
    acao VARCHAR(30) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_limite_envios ON limite_envios(acao, ip_hash, criado_em);

-- Recuperação de senha (guarda só o hash do token)
CREATE TABLE IF NOT EXISTS redefinicoes_senha (
    id BIGSERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_em TIMESTAMP NOT NULL,
    usado BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Auditoria: registra quem alterou ou excluiu algo no painel administrativo
CREATE TABLE IF NOT EXISTS auditoria (
    id BIGSERIAL PRIMARY KEY,
    admin_email VARCHAR(150) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    alvo VARCHAR(100) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_auditoria_criado_em ON auditoria(criado_em);

-- Confirmação de e-mail no cadastro
-- Contas antigas ficam confirmadas (DEFAULT TRUE só para linhas que já existem).
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email_confirmado BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE usuarios ALTER COLUMN email_confirmado SET DEFAULT FALSE;
CREATE TABLE IF NOT EXISTS confirmacoes_email (
    id BIGSERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_em TIMESTAMP NOT NULL,
    usado BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Coordenadas do GPS no cadastro de pets (opcionais, arredondadas para 3 casas)
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION;
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION;

-- Fluxo de adoção
-- Responsável pelo animal (conta de quem cadastrou). NULL para cadastros antigos.
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;
CREATE TABLE IF NOT EXISTS solicitacoes_adocao (
    id BIGSERIAL PRIMARY KEY,
    registro_pet_id INTEGER NOT NULL REFERENCES registros_pets(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    mensagem VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente'
        CHECK (status IN ('pendente', 'aprovada', 'recusada', 'cancelada', 'encerrada')),
    decidido_por VARCHAR(150),
    decidido_em TIMESTAMP,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- Uma solicitação pendente por pessoa e animal
CREATE UNIQUE INDEX IF NOT EXISTS uq_solicitacao_pendente
    ON solicitacoes_adocao(registro_pet_id, usuario_id) WHERE status = 'pendente';
CREATE INDEX IF NOT EXISTS idx_solicitacoes_pet ON solicitacoes_adocao(registro_pet_id);

-- Informações do animal preenchidas no cadastro (usadas no card da página de animais)
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS nome_animal VARCHAR(100);
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS especie VARCHAR(10);
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS idade VARCHAR(10);

-- Favoritos salvos na conta (um registro por conta e animal)
CREATE TABLE IF NOT EXISTS favoritos (
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    registro_pet_id INTEGER NOT NULL REFERENCES registros_pets(id) ON DELETE CASCADE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, registro_pet_id)
);
CREATE INDEX IF NOT EXISTS idx_favoritos_pet ON favoritos(registro_pet_id);
