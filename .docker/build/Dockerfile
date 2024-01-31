FROM php:8.1

RUN apt-get update && apt-get install -y \
    git \
    libsodium-dev \
    libicu-dev \
    librabbitmq-dev \
    libcurl4-openssl-dev \
    zlib1g-dev \
    libpng-dev \
    libonig-dev \
    libpq-dev \
    libzip-dev \
    supervisor

RUN docker-php-ext-install pdo pdo_mysql sodium intl curl fileinfo gd zip

RUN apt clean && rm -rf /var/lib/apt/lists/*

COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

#RUN git clone https://github.com/MasterKafey/MailCatcher.git /var/www/html
COPY . /app

WORKDIR /app

RUN composer install --optimize-autoloader

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]