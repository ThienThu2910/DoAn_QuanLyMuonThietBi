FROM php:8.2-apache

# MinIO client dùng cURL; ứng dụng dùng PDO MySQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql curl fileinfo \
    && rm -rf /var/lib/apt/lists/*

# Bật rewrite module cho Apache
RUN a2enmod rewrite

# Thư mục làm việc và phân quyền
WORKDIR /var/www/html
COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
