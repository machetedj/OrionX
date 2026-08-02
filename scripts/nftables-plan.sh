#!/usr/bin/env bash
set -Eeuo pipefail
ADMIN_IP="${ADMIN_IP:-}"
[[ "$ADMIN_IP" =~ ^[0-9a-fA-F:.]+$ ]] || { echo "Define ADMIN_IP"; exit 1; }
cat <<EOF
table inet media_panel {
 chain input {
  type filter hook input priority 0; policy drop;
  ct state established,related accept
  iif lo accept
  ip saddr $ADMIN_IP tcp dport 35222 accept
  tcp dport { 80, 443 } accept
 }
 chain forward { type filter hook forward priority 0; policy drop; }
 chain output { type filter hook output priority 0; policy accept; }
}
EOF
