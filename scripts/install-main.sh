#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

[[ $EUID -eq 0 ]] || { echo "ERROR: el instalador interno requiere root."; exit 1; }
[[ -r /etc/os-release ]] || { echo "ERROR: no se pudo detectar el sistema."; exit 1; }
source /etc/os-release
[[ "${ID:-}" == ubuntu ]] || { echo "ERROR: se requiere Ubuntu Server."; exit 1; }
dpkg --compare-versions "${VERSION_ID:-0}" ge 24.04 || { echo "ERROR: se requiere Ubuntu 24.04 o posterior."; exit 1; }

SOURCE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR=/opt/licensed-media-panel
APP_USER=media-panel
PHP_VERSION="${PHP_VERSION:-8.5}"
DB_MODE=local
PANEL_DOMAIN="${PANEL_IP:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@panel.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
TLS_EMAIL=""
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-licensed_media_panel}"
DB_USERNAME="${DB_USERNAME:-panel_app}"
DB_PASSWORD="${DB_PASSWORD:-}"
INITIAL_INSTALL=0

install_latest_ffmpeg() {
  local releases_url=https://ffmpeg.org/releases
  local download_page_url=https://ffmpeg.org/download.html
  local signing_key_url=https://ffmpeg.org/ffmpeg-devel.asc
  local signing_fingerprint=FCF986EA15E6E293A5644F10B4322F04D67658D8
  local page archive version installed work_dir key_dir actual_fingerprint verified
  apt-get update
  apt-get install -y build-essential pkg-config nasm yasm curl xz-utils gnupg ca-certificates \
    libx264-dev libx265-dev libvpx-dev libopus-dev libass-dev libfreetype6-dev \
    libmp3lame-dev libvorbis-dev libssl-dev zlib1g-dev
  page="$(curl --proto '=https' --tlsv1.2 --fail --silent --show-error --location "$download_page_url")"
  archive="$(printf '%s' "$page" | grep -oE 'ffmpeg-[0-9]+\.[0-9]+(\.[0-9]+)?\.tar\.xz' | sort -Vu | tail -n 1)"
  [[ "$archive" =~ ^ffmpeg-[0-9]+\.[0-9]+(\.[0-9]+)?\.tar\.xz$ ]] || { echo "No se pudo determinar la versión estable de FFmpeg"; return 1; }
  version="${archive#ffmpeg-}"; version="${version%.tar.xz}"
  installed="$(/usr/local/bin/ffmpeg -version 2>/dev/null | awk 'NR==1{print $3}' || true)"
  if [[ "$installed" == "$version" ]]; then echo "FFmpeg ${version} ya está instalado"; return 0; fi
  work_dir="$(mktemp -d)"; key_dir="$(mktemp -d)"; chmod 0700 "$key_dir"
  curl --proto '=https' --tlsv1.2 -fsSL "$releases_url/$archive" -o "$work_dir/$archive"
  curl --proto '=https' --tlsv1.2 -fsSL "$releases_url/$archive.asc" -o "$work_dir/$archive.asc"
  curl --proto '=https' --tlsv1.2 -fsSL "$signing_key_url" -o "$work_dir/ffmpeg-devel.asc"
  actual_fingerprint="$(GNUPGHOME="$key_dir" gpg --batch --with-colons --import-options show-only --import "$work_dir/ffmpeg-devel.asc" 2>/dev/null | awk -F: '$1=="fpr"{print $10;exit}')"
  [[ "$actual_fingerprint" == "$signing_fingerprint" ]] || { rm -rf "$work_dir" "$key_dir"; echo "Firma de FFmpeg inválida"; return 1; }
  GNUPGHOME="$key_dir" gpg --batch --import "$work_dir/ffmpeg-devel.asc" >/dev/null 2>&1
  GNUPGHOME="$key_dir" gpg --batch --verify "$work_dir/$archive.asc" "$work_dir/$archive"
  tar -C "$work_dir" -xf "$work_dir/$archive"
  pushd "$work_dir/ffmpeg-$version" >/dev/null
  ./configure --prefix=/usr/local --bindir=/usr/local/bin --disable-debug --disable-doc \
    --enable-gpl --enable-openssl --enable-libx264 --enable-libx265 --enable-libvpx \
    --enable-libopus --enable-libass --enable-libfreetype --enable-libmp3lame --enable-libvorbis
  make -j"$(getconf _NPROCESSORS_ONLN)"; make install; popd >/dev/null; hash -r
  verified="$(/usr/local/bin/ffmpeg -version | awk 'NR==1{print $3}')"
  [[ "$verified" == "$version" ]] || { rm -rf "$work_dir" "$key_dir"; echo "FFmpeg no coincide con la versión esperada"; return 1; }
  /usr/local/bin/ffprobe -version >/dev/null
  rm -rf "$work_dir" "$key_dir"
  echo "FFMPEG_INSTALL_OK version=${version}"
}

[[ "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]] || { echo "ERROR: PHP_VERSION inválida."; exit 1; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_]+$ && "$DB_USERNAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "ERROR: nombre de base o usuario inválido."; exit 1; }

valid_ipv4() { local part; IFS=. read -r -a parts <<< "$1"; [[ ${#parts[@]} -eq 4 ]] || return 1; for part in "${parts[@]}"; do [[ "$part" =~ ^[0-9]+$ && "$part" -le 255 ]] || return 1; done; }
if [[ -z "$PANEL_DOMAIN" ]] && command -v curl >/dev/null 2>&1; then PANEL_DOMAIN="$(curl -4fsS --max-time 8 https://api.ipify.org || true)"; fi
if [[ -z "$PANEL_DOMAIN" ]]; then PANEL_DOMAIN="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1);exit}}')"; fi
valid_ipv4 "$PANEL_DOMAIN" || { echo "ERROR: no se pudo detectar la IP IPv4 del servidor."; exit 1; }
if [[ -t 0 ]]; then
  clear
  echo "============================================================"
  echo "       INSTALACIÓN AUTOMÁTICA DEL SERVIDOR PRINCIPAL"
  echo "============================================================"
  echo "IP detectada:       ${PANEL_DOMAIN}"
  echo "Ubuntu:             ${VERSION_ID}"
  echo "PHP:                ${PHP_VERSION}"
  echo "Base de datos:      MariaDB local automática"
  echo "Administrador:      generado automáticamente"
  echo "============================================================"
  echo "No cierres esta ventana hasta ver las credenciales finales."
  sleep 2
fi
[[ "$DB_PORT" =~ ^[0-9]+$ && "$DB_PORT" -ge 1 && "$DB_PORT" -le 65535 ]] || { echo "ERROR: puerto de base inválido."; exit 1; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_]+$ && "$DB_USERNAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "ERROR: nombre de base o usuario inválido."; exit 1; }

command -v apt-get >/dev/null || { echo "ERROR: apt-get no está disponible."; exit 1; }
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l
apt-get update
apt-get install -y ca-certificates curl openssl software-properties-common lsb-release gnupg
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update
fi

PACKAGES=(nginx redis-server "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-redis" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-zip" unzip composer supervisor cron sudo ufw nfs-common cifs-utils certbot python3-certbot-nginx mariadb-client)
[[ "$DB_MODE" == local ]] && PACKAGES+=(mariadb-server)
apt-get install -y "${PACKAGES[@]}"
install_latest_ffmpeg

id "$APP_USER" >/dev/null 2>&1 || useradd --system --home "$APP_DIR" --shell /usr/sbin/nologin "$APP_USER"
install -d -o "$APP_USER" -g www-data -m 0750 "$APP_DIR"
cp -a "$SOURCE_DIR/." "$APP_DIR/"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
  INITIAL_INSTALL=1
  APP_KEY="$(openssl rand -hex 32)"
  CREDENTIALS_KEY="$(openssl rand -base64 32)"
  MEDIA_SIGNING_KEY="$(openssl rand -base64 32)"
  [[ -n "$DB_PASSWORD" ]] || DB_PASSWORD="$(openssl rand -hex 24)"
  [[ -n "$ADMIN_PASSWORD" ]] || ADMIN_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=')Aa1!"
  cat > .env <<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${PANEL_DOMAIN}
APP_KEY=${APP_KEY}
CREDENTIALS_KEY=${CREDENTIALS_KEY}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
SESSION_SECURE=true
LOG_LEVEL=warning
FFPROBE_PATH=/usr/local/bin/ffprobe
FFPROBE_TIMEOUT=30
FFMPEG_PATH=/usr/local/bin/ffmpeg
TMDB_BEARER_TOKEN=
TMDB_DEFAULT_LANGUAGE=es-ES
TMDB_CACHE_TTL=86400
TMDB_RATE_LIMIT=30
MEDIA_SIGNING_KEY_ID=v1
MEDIA_SIGNING_KEY=${MEDIA_SIGNING_KEY}
MEDIA_SIGNING_KEYS={}
MEDIA_TOKEN_TTL=120
BALANCER_AGENT_VERSION=1.0.0
BALANCER_PHP_VERSION=${PHP_VERSION}
ENV
  chmod 0640 .env
fi

env_value() { awk -F= -v wanted="$1" '$1==wanted {print substr($0,index($0,"=")+1); exit}' .env; }
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"
if [[ "$DB_HOST" != 127.0.0.1 && "$DB_HOST" != localhost ]]; then DB_MODE=external; fi
if [[ "$DB_MODE" == local ]]; then
  install -o root -g root -m 0644 config/mariadb-hardening.cnf /etc/mysql/mariadb.conf.d/60-media-panel-hardening.cnf
  systemctl enable --now mariadb
  DB_SETUP_FILE="$(mktemp)"
  chmod 0600 "$DB_SETUP_FILE"
  cat > "$DB_SETUP_FILE" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
  mariadb < "$DB_SETUP_FILE"
  rm -f "$DB_SETUP_FILE"
else
  mariadb-admin --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" ping --connect-timeout=10 >/dev/null
fi

install -d -o "$APP_USER" -g www-data -m 0770 storage/logs storage/cache storage/sessions storage/imports/xui
install -d -o root -g www-data -m 0750 /etc/nginx/media-libraries.d
chown -R "$APP_USER":www-data "$APP_DIR"
chmod 0640 "$APP_DIR/.env"
sudo -u "$APP_USER" /usr/bin/php scripts/migrate.php
if [[ $INITIAL_INSTALL -eq 1 ]]; then
  sudo -u "$APP_USER" /usr/bin/php scripts/create-admin.php "$ADMIN_EMAIL" "$ADMIN_PASSWORD"
  CREDENTIAL_FILE=/root/.media-panel-initial-credentials
  printf 'Administrador: %s\nContraseña inicial: %s\n' "$ADMIN_EMAIL" "$ADMIN_PASSWORD" > "$CREDENTIAL_FILE"
  chmod 0600 "$CREDENTIAL_FILE"
fi

install -d -o root -g root -m 0755 /etc/ssl/media-panel
if [[ ! -f /etc/ssl/media-panel/fullchain.pem || ! -f /etc/ssl/media-panel/privkey.pem ]]; then
  openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 30 -subj "/CN=${PANEL_DOMAIN}" -addext "subjectAltName=IP:${PANEL_DOMAIN}" -keyout /etc/ssl/media-panel/privkey.pem -out /etc/ssl/media-panel/fullchain.pem
  chmod 0600 /etc/ssl/media-panel/privkey.pem
fi
sed "s/__PHP_VERSION__/${PHP_VERSION}/g; s/server_name _;/server_name ${PANEL_DOMAIN};/; /listen 80;/a\    listen 443 ssl;\n    ssl_certificate /etc/ssl/media-panel/fullchain.pem;\n    ssl_certificate_key /etc/ssl/media-panel/privkey.pem;" config/nginx.conf > /etc/nginx/sites-available/licensed-media-panel
cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/media-panel.conf" <<FPM
[media-panel]
user = ${APP_USER}
group = www-data
listen = /run/php/php${PHP_VERSION}-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 30
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
php_admin_value[open_basedir] = ${APP_DIR}:/tmp:/usr/share/php
php_admin_value[session.save_path] = ${APP_DIR}/storage/sessions
php_admin_value[upload_max_filesize] = 5G
php_admin_value[post_max_size] = 5G
php_admin_value[max_execution_time] = 600
php_admin_value[max_input_time] = 600
php_admin_flag[display_errors] = off
FPM
rm -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
cp config/supervisor-worker.conf /etc/supervisor/conf.d/media-panel-worker.conf
cp config/media-panel.cron /etc/cron.d/media-panel
chmod 0644 /etc/cron.d/media-panel
install -o root -g root -m 0750 scripts/cert-helper.php /usr/local/sbin/media-panel-cert-helper
install -o root -g root -m 0750 scripts/xui-sql-helper.php /usr/local/sbin/orionx-xui-sql-helper
install -o root -g root -m 0440 config/media-panel-sudoers /etc/sudoers.d/media-panel-certificates
visudo -cf /etc/sudoers.d/media-panel-certificates
ln -sfn /etc/nginx/sites-available/licensed-media-panel /etc/nginx/sites-enabled/licensed-media-panel
rm -f /etc/nginx/sites-enabled/default

SSHD_PORT="$(sshd -T 2>/dev/null | awk '$1=="port"{print $2;exit}')"
[[ "$SSHD_PORT" =~ ^[0-9]+$ ]] || SSHD_PORT=22
ufw allow "${SSHD_PORT}/tcp"
ufw allow 'Nginx Full'
ufw --force enable

nginx -t
systemctl enable --now nginx redis-server "php${PHP_VERSION}-fpm" supervisor cron
supervisorctl reread
supervisorctl update

echo "TLS temporal configurado para la IP. El dominio y Let's Encrypt se administran después desde el dashboard."

sudo -u "$APP_USER" /usr/bin/php scripts/verify-installation.php
curl --fail --silent --show-error --max-time 10 --resolve "${PANEL_DOMAIN}:443:127.0.0.1" "https://${PANEL_DOMAIN}/login" --insecure >/dev/null

echo
echo "============================================================"
echo "Panel instalado: https://${PANEL_DOMAIN}"
if [[ -f /root/.media-panel-initial-credentials ]]; then
  cat /root/.media-panel-initial-credentials
  echo "GUARDA ESTA CONTRASEÑA AHORA. NO VOLVERÁ A MOSTRARSE."
  if command -v shred >/dev/null; then shred -u /root/.media-panel-initial-credentials; else rm -f /root/.media-panel-initial-credentials; fi
else
  echo "Actualización completada. Las credenciales existentes no cambiaron."
fi
echo "Ubuntu ${VERSION_ID}; PHP ${PHP_VERSION}; DB ${DB_MODE}."
echo "============================================================"
