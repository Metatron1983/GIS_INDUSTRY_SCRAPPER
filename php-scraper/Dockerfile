FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libcurl4-openssl-dev libssl-dev poppler-utils \
    libpng-dev \
    && docker-php-ext-install pdo_mysql zip xml curl bcmath gd

# Копируем composer.phar (должен лежать рядом с Dockerfile)
COPY composer.phar /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

WORKDIR /var/www/html

# Копируем composer.json и устанавливаем зависимости
COPY src/composer.json /var/www/html/composer.json
RUN composer install --no-interaction --prefer-dist

# Копируем остальной код
COPY src/ /var/www/html/src/

RUN mkdir -p /var/www/storage/documents /var/www/storage/reports

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html"]