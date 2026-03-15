FROM jrmgx/php-8.4-fpm-nginx:2-trailodyssey AS main
FROM main AS copy

COPY . /var/www/

COPY docker/php-fpm/etc/ImageMagick-6 /etc/ImageMagick-6
COPY docker/php-fpm/usr/local/etc /usr/local/etc
COPY docker/php-fpm/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm/entrypoint.sh /usr/local/bin/
