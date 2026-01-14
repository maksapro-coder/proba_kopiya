FROM php:8.4-apache

# Устанавливаем зависимости для PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        && docker-php-ext-install pdo_pgsql

# Копируем проект в контейнер
COPY . /var/www/html/

# Включаем модуль apache rewrite
RUN a2enmod rewrite
