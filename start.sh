#!/bin/bash

set -e

if [ ! -f .env ]; then
    echo "[start] .env not found — copying from .env.example"
    cp .env.example .env
fi

docker compose up -d

echo "[start] waiting for MySQL to be ready..."
until docker compose exec db mysql -u root -pez-php -e "SELECT 1;" >/dev/null 2>&1; do
    sleep 1
done

docker compose exec app bash
