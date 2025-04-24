# Используем официальный образ PHP
FROM php:8.0-apache

# Устанавливаем необходимые расширения для PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Копируем локальные файлы в контейнер
COPY . /var/www/html/

# Настраиваем рабочую директорию
WORKDIR /var/www/html

# Устанавливаем права доступа
RUN chown -R www-data:www-data /var/www/html

# Установка Composer (если необходимо)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем зависимости через Composer (если есть)
RUN composer install
