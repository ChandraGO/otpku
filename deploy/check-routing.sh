#!/usr/bin/env bash
set -euo pipefail
STACK_DIR="${STACK_DIR:-/opt/kodeotp}"
readv(){ grep -E "^$1=" "$STACK_DIR/.env" 2>/dev/null | tail -1 | cut -d= -f2- || true; }
DOMAIN="$(readv KODEOTP_DOMAIN)"; DOMAIN="${DOMAIN:-otp.example.com}"
HOST="$(readv KODEOTP_GATEWAY_HOST)"; HOST="${HOST:-127.0.0.1}"
PORT="$(readv KODEOTP_GATEWAY_PORT)"; PORT="${PORT:-3280}"
check(){ local label="$1" url="$2"; echo "[check] $label $url"; curl -fsS --max-time 15 "$url" | grep -q '"service":"kodeotp"'; }
LOCAL=0; PUBLIC=0
check lokal "http://$HOST:$PORT/healthz" && LOCAL=1 || true
check publik "https://$DOMAIN/healthz" && PUBLIC=1 || true
if [ "$LOCAL" -eq 1 ] && [ "$PUBLIC" -eq 1 ]; then echo 'OK: routing lokal dan publik benar.'; exit 0; fi
if [ "$LOCAL" -eq 1 ]; then echo 'Aplikasi hidup, tetapi DNS/Caddy utama belum mengarah ke gateway lokal.'; exit 2; fi
echo 'Gateway lokal belum sehat. Periksa docker compose dan log container.'; exit 1
