# Используем официальный образ PHP
FROM php:8.0-apache

# Устанавливаем необходимые расширения для PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Устанавливаем Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Копируем локальные файлы в контейнер
COPY . /var/www/html/

# Настраиваем рабочую директорию
WORKDIR /var/www/html

# Устанавливаем права доступа
RUN chown -R www-data:www-data /var/www/html

# Устанавливаем зависимости через Composer
RUN composer install
