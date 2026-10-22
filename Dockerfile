FROM php:8.3-apache

# Instala as dependências de compilação E o cliente psql (postgresql-client)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Instala as extensões PHP para o PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

COPY . . 

RUN mkdir -p /var/www/html/uploads/pets \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

CMD ["sh", "/var/www/html/init.sh"]

EXPOSE 80