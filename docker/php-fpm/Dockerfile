# see https://gist.github.com/jrmgx/fd96fe94eddba4eaf90b4158c9c3f1d7
FROM jrmgx/php-8.2-fpm-nginx:4 AS main

# Install PostgreSQL PDO extension
RUN apt update && apt install -y --no-install-recommends \
        libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt clean

# Install PostgreSQL tooling
RUN apt update && apt install -y --no-install-recommends \
        wget lsb-release \
    && sh -c 'echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list' \
    && wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add - \
    && apt update && apt install -y --no-install-recommends \
        postgresql-client-17 \
    && apt clean

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

COPY docker/php-fpm/entrypoint.sh /usr/local/bin/

ENTRYPOINT ["entrypoint.sh"]

WORKDIR "/var/www"

EXPOSE 80

CMD ["php-fpm"]

FROM main AS copy

COPY . /var/www/

ENTRYPOINT ["entrypoint.sh"]

WORKDIR "/var/www"

EXPOSE 80

CMD ["php-fpm"]
