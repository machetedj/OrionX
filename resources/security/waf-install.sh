#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || exit 77
config_file="${1:-}"
[[ -f "$config_file" ]] || exit 64
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y libnginx-mod-http-modsecurity modsecurity-crs
install -d -o root -g root -m 0755 /etc/modsecurity
backup_dir="$(mktemp -d /tmp/orionx-waf-backup.XXXXXX)"
trap 'rm -rf "$backup_dir"' EXIT
[[ -f /etc/modsecurity/orionx.conf ]] && cp /etc/modsecurity/orionx.conf "$backup_dir/orionx.conf"
[[ -f /etc/nginx/conf.d/orionx-waf.conf ]] && cp /etc/nginx/conf.d/orionx-waf.conf "$backup_dir/nginx.conf"
install -o root -g root -m 0644 "$config_file" /etc/modsecurity/orionx.conf
printf '%s\n' 'modsecurity on;' 'modsecurity_rules_file /etc/modsecurity/orionx.conf;' > /etc/nginx/conf.d/orionx-waf.conf
touch /var/log/nginx/modsec_audit.log
chown www-data:adm /var/log/nginx/modsec_audit.log
chmod 0640 /var/log/nginx/modsec_audit.log
if ! nginx -t; then
  if [[ -f "$backup_dir/orionx.conf" ]]; then cp "$backup_dir/orionx.conf" /etc/modsecurity/orionx.conf; else rm -f /etc/modsecurity/orionx.conf; fi
  if [[ -f "$backup_dir/nginx.conf" ]]; then cp "$backup_dir/nginx.conf" /etc/nginx/conf.d/orionx-waf.conf; else rm -f /etc/nginx/conf.d/orionx-waf.conf; fi
  nginx -t || true
  exit 70
fi
systemctl reload nginx
echo WAF_DEPLOY_OK
