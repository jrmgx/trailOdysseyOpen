# see https://gist.github.com/jrmgx/79d9776d5efc7d2ca45e64cc14a0e095
FROM jrmgx/php-8.2-fpm-nginx:4-trailodyssey AS main
FROM main AS copy

COPY . /var/www/
COPY docker/php-fpm/etc/ImageMagick-6 /etc/ImageMagick-6
COPY docker/php-fpm/usr/local/etc /usr/local/etc
COPY docker/php-fpm/nginx.conf /etc/nginx/nginx.conf
