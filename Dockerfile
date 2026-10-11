FROM php:8.2-apache

# Cài đặt extension PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Bật rewrite module cho Apache
RUN a2enmod rewrite

# Thư mục làm việc và phân quyền
WORKDIR /var/www/html
COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80