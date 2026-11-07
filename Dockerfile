FROM php:8.2-apache

ENV APP_ENV=production

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && docker-php-ext-install pdo_mysql \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/

RUN cp /var/www/html/apache-security.conf /etc/apache2/conf-available/warung-security.conf \
    && rm /var/www/html/apache-security.conf \
    && a2enconf warung-security \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} +

EXPOSE 80
CMD ["apache2-foreground"]
