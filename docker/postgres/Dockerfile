FROM postgres:17

# Install necessary dependencies and extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-17-postgis-3 \
        postgresql-17-postgis-3-scripts \
        postgresql-17-pgrouting \
    && rm -rf /var/lib/apt/lists/*

# Initialize PostGIS and pgRouting
RUN echo "CREATE EXTENSION postgis; CREATE EXTENSION pgrouting;" > /docker-entrypoint-initdb.d/init-extensions.sql
