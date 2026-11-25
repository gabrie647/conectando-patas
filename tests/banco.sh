#!/usr/bin/env bash
# Testa o banco: aplica o banco.sql DUAS vezes (precisa ser idempotente) e confere o esquema.
# Uso: TEST_DATABASE_URL=postgres://... bash tests/banco.sh
set -u
: "${TEST_DATABASE_URL:?Defina TEST_DATABASE_URL, ex.: postgres://usuario:senha@localhost:5432/banco}"
ARQ="$(dirname "$0")/../data-base/banco.sql"
falhas=0

psql "$TEST_DATABASE_URL" -v ON_ERROR_STOP=1 -q -f "$ARQ" || { echo "FALHA  primeira aplicação do banco.sql"; exit 1; }
psql "$TEST_DATABASE_URL" -v ON_ERROR_STOP=1 -q -f "$ARQ" || { echo "FALHA  segunda aplicação (não idempotente)"; exit 1; }
echo "OK     banco.sql aplicado duas vezes sem erro"

checar() {
    local nome="$1" consulta="$2" esperado="$3" obtido
    obtido=$(psql "$TEST_DATABASE_URL" -tAc "$consulta" 2>/dev/null | tr -d ' ')
    if [ "$obtido" = "$esperado" ]; then
        echo "OK     $nome"
    else
        echo "FALHA  $nome (esperado $esperado, veio '$obtido')"
        falhas=$((falhas + 1))
    fi
}

for tabela in usuarios registros_pets contatos redefinicoes_senha limite_envios auditoria confirmacoes_email solicitacoes_adocao; do
    checar "tabela $tabela" "select count(*) from information_schema.tables where table_schema='public' and table_name='$tabela'" 1
done

checar "usuarios.email_confirmado" "select count(*) from information_schema.columns where table_name='usuarios' and column_name='email_confirmado'" 1
checar "registros_pets.latitude" "select count(*) from information_schema.columns where table_name='registros_pets' and column_name='latitude'" 1
checar "registros_pets.usuario_id" "select count(*) from information_schema.columns where table_name='registros_pets' and column_name='usuario_id'" 1
checar "índice de solicitação pendente única" "select count(*) from pg_indexes where indexname='uq_solicitacao_pendente'" 1
checar "status de solicitação com CHECK" "select count(*) from information_schema.check_constraints where check_clause like '%pendente%'" 1

echo
if [ "$falhas" -eq 0 ]; then echo "Banco ok."; else echo "$falhas verificação(ões) falharam."; fi
exit "$falhas"
