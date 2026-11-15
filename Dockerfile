FROM php:8.3-apache

# Instala as dependências de compilação E o cliente psql (postgresql-client)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Instala as extensões PHP para o PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

COPY . . 

# Bloqueia .git, banco.sql e arquivos internos no navegador
# Erros do PHP vão para os logs (stderr = aba Logs do Render) e não para a página,
# evitando que avisos/erros HTML quebrem a resposta JSON enviada ao navegador.
RUN printf "display_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\n" \
    > /usr/local/etc/php/conf.d/conectando-patas.ini

RUN a2enmod headers \
    && cp /var/www/html/apache-protecao.conf /etc/apache2/conf-available/protecao.conf \
    && a2enconf protecao

# Código somente leitura para o Apache; só a pasta de uploads é gravável.
RUN mkdir -p /var/www/html/uploads/pets \
    && chown -R root:root /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

CMD ["sh", "/var/www/html/init.sh"]

EXPOSE 80