FROM php:8.3-apache

# Dependências de compilação e cliente psql
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensões PHP: compila só as que a imagem base não traz
# (mbstring costuma já vir na imagem; gd e curl precisam ser compiladas quando faltam)
RUN docker-php-ext-install pdo pdo_pgsql \
    && (php -m | grep -qx mbstring || docker-php-ext-install mbstring) \
    && (php -m | grep -qx curl || docker-php-ext-install curl) \
    && (php -m | grep -qx gd || (docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install gd))

# Falha o build se alguma extensão exigida pelo código ainda estiver faltando
RUN php -m | grep -qx mbstring \
    && php -m | grep -qx gd \
    && php -m | grep -qx curl \
    && php -m | grep -qx pdo_pgsql

COPY . .

# Configuração do PHP:
# - erros vão para os logs (stderr = aba Logs do Render), nunca para a página
# - uploads: o formulário aceita 5 MB, então o PHP precisa aceitar um pouco mais
RUN printf "display_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\nupload_max_filesize=6M\npost_max_size=8M\nmemory_limit=256M\n" \
    > /usr/local/etc/php/conf.d/conectando-patas.ini

RUN a2enmod headers \
    && cp /var/www/html/apache-protecao.conf /etc/apache2/conf-available/protecao.conf \
    && a2enconf protecao

# Código somente leitura para o Apache; só a pasta de uploads é gravável
RUN mkdir -p /var/www/html/uploads/pets \
    && chown -R root:root /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/backend/saude.php') === false ? 1 : 0);"

CMD ["sh", "/var/www/html/init.sh"]

EXPOSE 80
