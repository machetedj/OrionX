#!/usr/bin/env bash
set -Eeuo pipefail
ADMIN_IP="${ADMIN_IP:-}"
MODE="${1:---plan}"
[[ "$ADMIN_IP" =~ ^[0-9a-fA-F:.]+$ ]] || { echo "Define ADMIN_IP con la IP administrativa actual"; exit 1; }
CURRENT_IP="${SSH_CONNECTION%% *}"
if [[ "$MODE" == "--apply" && -n "${SSH_CONNECTION:-}" && "$CURRENT_IP" != "$ADMIN_IP" ]]; then echo "Se rechaza: ADMIN_IP no coincide con la conexión SSH actual"; exit 1; fi
COMMANDS=("ufw default deny incoming" "ufw default allow outgoing" "ufw allow from $ADMIN_IP to any port 22 proto tcp" "ufw allow 80/tcp" "ufw allow 443/tcp" "ufw deny 3306/tcp" "ufw --force enable")
printf '%s\n' "${COMMANDS[@]}"
if [[ "$MODE" == "--apply" ]]; then for command in "${COMMANDS[@]}"; do read -r -a args <<< "$command"; "${args[@]}"; done; fi
