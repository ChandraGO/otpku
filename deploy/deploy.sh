#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/dapetotp/app}"
ENV_FILE="${ENV_FILE:-/opt/dapetotp/production.env}"
BRANCH="${BRANCH:-main}"
COMPOSE_FILE="${COMPOSE_FILE:-$APP_DIR/compose.yaml}"

exec 9>/var/lock/dapetotp-deploy.lock
if ! flock -n 9; then
  echo "Deploy lain sedang berjalan."
  exit 1
fi

cd "$APP_DIR"

git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"

docker compose --env-file "$ENV_FILE" -p dapetotp -f "$COMPOSE_FILE" build --pull
docker compose --env-file "$ENV_FILE" -p dapetotp -f "$COMPOSE_FILE" up -d --remove-orphans

docker compose --env-file "$ENV_FILE" -p dapetotp -f "$COMPOSE_FILE" ps

for i in $(seq 1 30); do
  if curl -fsS http://127.0.0.1:3281/ >/dev/null; then
    echo "Deploy OK: frontend merespons di 127.0.0.1:3281"
    exit 0
  fi
  sleep 2
done

echo "Health check gagal."
docker compose --env-file "$ENV_FILE" -p dapetotp -f "$COMPOSE_FILE" logs --tail=150
exit 1
