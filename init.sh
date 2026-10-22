#!/bin/sh
set -eu

if [ -z "${DATABASE_URL:-}" ]; then
  echo "ERRO: configure DATABASE_URL nas variáveis de ambiente do serviço web."
  exit 1
fi

echo "Aguardando PostgreSQL..."
attempt=1
until psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -c 'SELECT 1' >/dev/null 2>&1; do
  if [ "$attempt" -ge 12 ]; then
    echo "ERRO: Não foi possível conectar ao PostgreSQL após 12 tentativas."
    exit 1
  fi
  attempt=$((attempt + 1))
  sleep 2
done

echo "Verificando estrutura do banco..."
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f /var/www/html/data-base/banco.sql

echo "Iniciando Apache..."
exec apache2-foreground
