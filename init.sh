#!/bin/sh

# Interrompe a execução imediatamente se qualquer comando falhar
set -e

# Verifica se a DATABASE_URL do Render foi configurada
if [ -z "$DATABASE_URL" ]; then
  echo "ERRO CRÍTICO: A variável DATABASE_URL não está configurada no Render!"
  exit 1
fi

echo "Rodando o arquivo banco.sql no banco de dados do Render..."
psql "$DATABASE_URL" -f data-base/banco.sql

echo "Banco de dados atualizado com sucesso!"

# Inicia o Apache em primeiro plano
echo "Iniciando o servidor Web Apache..."
exec apache2-foreground