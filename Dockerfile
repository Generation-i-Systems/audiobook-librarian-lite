FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite-dev \
    && docker-php-ext-install pdo_sqlite pdo_mysql opcache

WORKDIR /app

COPY . .
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
VOLUME ["/app/storage"]

ENTRYPOINT ["/entrypoint.sh"]
