#!/bin/sh
# Creates the test database expected by phpunit.xml on first Postgres boot.
# Postgres-alpine runs every *.sh / *.sql in /docker-entrypoint-initdb.d/ once,
# right after the primary database (POSTGRES_DB) is initialized.

set -e

psql -v ON_ERROR_STOP=1 --username "${POSTGRES_USER}" --dbname "${POSTGRES_DB}" <<-EOSQL
    CREATE DATABASE trypost_test;
    GRANT ALL PRIVILEGES ON DATABASE trypost_test TO "${POSTGRES_USER}";
EOSQL
