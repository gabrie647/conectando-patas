#!/usr/bin/env bash
# Teste de fumaça: confere se o site publicado responde e se as proteções estão ativas.
# Uso: bash tests/smoke.sh https://seu-site.onrender.com
set -u
BASE="${1:?Uso: bash tests/smoke.sh https://seu-site}"
BASE="${BASE%/}"
falhas=0

checar_codigo() {
    local nome="$1" esperado="$2" url="$3" codigo
    codigo=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$url")
    if [ "$codigo" = "$esperado" ]; then
        echo "OK     $nome ($codigo)"
    else
        echo "FALHA  $nome (esperado $esperado, veio $codigo)"
        falhas=$((falhas + 1))
    fi
}

checar_cabecalho() {
    local cabecalho="$1"
    if curl -sI --max-time 20 "$BASE/" | grep -qi "^$cabecalho:"; then
        echo "OK     cabeçalho $cabecalho"
    else
        echo "FALHA  cabeçalho $cabecalho ausente"
        falhas=$((falhas + 1))
    fi
}

checar_codigo "home" 200 "$BASE/"
checar_codigo "animais" 200 "$BASE/animais.html"
checar_codigo "saúde do sistema" 200 "$BASE/backend/saude.php"
checar_codigo "adoção exige login" 401 "$BASE/backend/adocao.php?acao=minhas"
checar_codigo "admin exige login" 401 "$BASE/backend/admin.php?acao=resumo"
checar_codigo "código interno bloqueado" 403 "$BASE/backend/conexao.php"
checar_codigo "banco de dados bloqueado" 403 "$BASE/data-base/banco.sql"
checar_codigo "testes bloqueados" 403 "$BASE/tests/testes.php"
checar_cabecalho "X-Frame-Options"
checar_cabecalho "Content-Security-Policy"
checar_cabecalho "X-Content-Type-Options"

echo
if [ "$falhas" -eq 0 ]; then
    echo "Tudo certo."
else
    echo "$falhas verificação(ões) falharam."
fi
exit "$falhas"
