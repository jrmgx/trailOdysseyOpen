# see https://gist.github.com/jrmgx/fd96fe94eddba4eaf90b4158c9c3f1d7
FROM jrmgx/php-8.2-fpm-nginx:4

# Node 20
RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" \
        | tee /etc/apt/sources.list.d/nodesource.list \
    && apt update && apt install -y --no-install-recommends \
        nodejs \
    && apt clean

# Yarn without Corepack
RUN npm install -g yarn

# PHP Tooling
RUN curl -sS https://getcomposer.org/installer | php -- \
    && mv composer.phar /usr/local/bin/composer

RUN curl "https://github.com/jolicode/castor/releases/latest/download/castor.linux-amd64.phar" -Lso /bin/castor \
    && chmod a+x /bin/castor

COPY entrypoint.sh /usr/local/bin/

ENTRYPOINT ["entrypoint.sh"]

WORKDIR "/var/www"

EXPOSE 80

CMD ["php-fpm"]
