FROM php:8.3-apache

# Instala as dependências do sistema necessárias para compilar o driver PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Instala as extensões PHP do PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

COPY . . 

RUN chown -R www-data:www-data /var/www/html

CMD sh init.sh

EXPOSE 80