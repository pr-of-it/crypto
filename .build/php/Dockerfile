FROM php:8.2-zts

RUN pecl channel-update pecl.php.net && \
    pecl install -f parallel && \
    docker-php-ext-enable parallel

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php
COPY . /app
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8082"]