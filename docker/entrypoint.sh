#!/bin/sh
set -e

# Railway fournit le port d'écoute dans $PORT ; le TLS est géré en amont.
export SERVER_NAME=":${PORT:-8080}"

if [ -z "$APP_SECRET" ]; then
    echo "ERREUR : la variable APP_SECRET doit être définie." >&2
    exit 1
fi

echo "Attente de la base de données..."
tries=0
until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "Base de données injoignable : vérifiez DATABASE_URL." >&2
        exit 1
    fi
    sleep 2
done

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Instance de démonstration : l'établissement fictif est chargé au premier démarrage
if [ "$APP_DEMO" = "1" ]; then
    php bin/console app:demo:load --if-empty
fi

php bin/console cache:warmup

exec "$@"
