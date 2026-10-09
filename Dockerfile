FROM php:8.3-apache

# Instala as dependências de compilação E o cliente psql (postgresql-client)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Instala as extensões PHP para o PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

COPY . . 

RUN chown -R www-data:www-data /var/www/html

CMD sh init.sh

EXPOSE 80