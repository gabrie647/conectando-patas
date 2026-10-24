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
ALTER TABLE registros_pets ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'pendente';
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
