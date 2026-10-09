#!/bin/sh

# Verifica se a DATABASE_URL do Render foi configurada
if [ -z "$DATABASE_URL" ]; then
  echo "ERRO: A variável DATABASE_URL não está configurada no Render!"
  exit 1
fi

echo "Rodando o arquivo banco.sql no banco de dados do Render..."
psql "$DATABASE_URL" -f data-base/banco.sql

# Mensagem de confirmação
if [ $? -eq 0 ]; then
  echo "Banco de dados atualizado com sucesso!"
else
  echo "Erro ao executar o script banco.sql"
fi

# Inicia o Apache em primeiro plano para manter o container ativo no Render
echo "Iniciando o servidor Web Apache..."
exec apache2-foreground