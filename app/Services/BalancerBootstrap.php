<?php
declare(strict_types=1);
namespace App\Services;
final readonly class BalancerBootstrap
{
 public function render():string{return <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Se requiere root"; exit 1; }
source /etc/os-release
[[ "$ID" == ubuntu ]] || { echo "Solo se admite Ubuntu Server"; exit 1; }
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
set -a; source "$BUNDLE_DIR/agent.env"; set +a
install_latest_ffmpeg() {
 local base=https://ffmpeg.org/releases page archive version installed work keys fingerprint verified
 apt-get update
 apt-get install -y build-essential pkg-config nasm yasm curl xz-utils gnupg ca-certificates libx264-dev libx265-dev libvpx-dev libopus-dev libass-dev libfreetype6-dev libmp3lame-dev libvorbis-dev libssl-dev zlib1g-dev
 page="$(curl --proto '=https' --tlsv1.2 -fsSL https://ffmpeg.org/download.html)"
 archive="$(printf '%s' "$page" | grep -oE 'ffmpeg-[0-9]+\.[0-9]+(\.[0-9]+)?\.tar\.xz' | sort -Vu | tail -n 1)"
 [[ "$archive" =~ ^ffmpeg-[0-9]+\.[0-9]+(\.[0-9]+)?\.tar\.xz$ ]] || return 1
 version="${archive#ffmpeg-}"; version="${version%.tar.xz}"
 installed="$(/usr/local/bin/ffmpeg -version 2>/dev/null | awk 'NR==1{print $3}' || true)"
 [[ "$installed" == "$version" ]] && { echo "FFmpeg ${version} ya está instalado"; return 0; }
 work="$(mktemp -d)"; keys="$(mktemp -d)"; chmod 0700 "$keys"
 curl --proto '=https' --tlsv1.2 -fsSL "$base/$archive" -o "$work/$archive"
 curl --proto '=https' --tlsv1.2 -fsSL "$base/$archive.asc" -o "$work/$archive.asc"
 curl --proto '=https' --tlsv1.2 -fsSL https://ffmpeg.org/ffmpeg-devel.asc -o "$work/key.asc"
 fingerprint="$(GNUPGHOME="$keys" gpg --batch --with-colons --import-options show-only --import "$work/key.asc" 2>/dev/null | awk -F: '$1=="fpr"{print $10;exit}')"
 [[ "$fingerprint" == FCF986EA15E6E293A5644F10B4322F04D67658D8 ]] || { rm -rf "$work" "$keys"; return 1; }
 GNUPGHOME="$keys" gpg --batch --import "$work/key.asc" >/dev/null 2>&1
 GNUPGHOME="$keys" gpg --batch --verify "$work/$archive.asc" "$work/$archive"
 tar -C "$work" -xf "$work/$archive"; pushd "$work/ffmpeg-$version" >/dev/null
 ./configure --prefix=/usr/local --bindir=/usr/local/bin --disable-debug --disable-doc --enable-gpl --enable-version3 --enable-openssl --enable-libx264 --enable-libx265 --enable-libvpx --enable-libopus --enable-libass --enable-libfreetype --enable-libmp3lame --enable-libvorbis
 make -j"$(getconf _NPROCESSORS_ONLN)"; make install; popd >/dev/null; hash -r
 verified="$(/usr/local/bin/ffmpeg -version | awk 'NR==1{print $3}')"
 [[ "$verified" == "$version" ]] && /usr/local/bin/ffprobe -version >/dev/null
 rm -rf "$work" "$keys"; [[ "$verified" == "$version" ]]
}
PHP_VERSION="${PHP_VERSION:-8.5}"
[[ "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]] || exit 1
INSTALL_DIR=/opt/media-balancer
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l
POLICY=/usr/sbin/policy-rc.d; BACKUP=/tmp/media-panel-policy.backup; EXISTED=0
[[ -f "$POLICY" ]] && { cp -a "$POLICY" "$BACKUP"; EXISTED=1; }
restore_policy(){ if [[ $EXISTED -eq 1 ]];then cp -a "$BACKUP" "$POLICY";else rm -f "$POLICY";fi;rm -f "$BACKUP"; }
trap restore_policy EXIT
printf '#!/bin/sh\nexit 101\n' > "$POLICY"; chmod 0755 "$POLICY"
apt-get update; apt-get install -y software-properties-common ca-certificates
if ! apt-cache show "php${PHP_VERSION}-cli" >/dev/null 2>&1;then add-apt-repository -y ppa:ondrej/php;apt-get update;fi
apt-get install -y nginx libnginx-mod-rtmp redis-server "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-curl" certbot python3-certbot-nginx
install_latest_ffmpeg; restore_policy; trap - EXIT
id media-balancer >/dev/null 2>&1 || useradd --system --home "$INSTALL_DIR" --shell /usr/sbin/nologin media-balancer
install -d -o root -g media-balancer -m 0755 "$INSTALL_DIR"
install -o root -g media-balancer -m 0750 "$BUNDLE_DIR/agent.php" "$INSTALL_DIR/agent.php"
install -o root -g media-balancer -m 0750 "$BUNDLE_DIR/media-task.php" "$INSTALL_DIR/media-task.php"
install -o root -g root -m 0644 "$BUNDLE_DIR/media-gateway.php" "$INSTALL_DIR/media-gateway.php"
install -o root -g media-balancer -m 0750 "$BUNDLE_DIR/rtmp-agent.php" "$INSTALL_DIR/rtmp-agent.php"
install -o root -g media-balancer -m 0750 "$BUNDLE_DIR/rtmp-task.php" "$INSTALL_DIR/rtmp-task.php"
install -o root -g media-balancer -m 0640 "$BUNDLE_DIR/agent.env" "$INSTALL_DIR/agent.env"
install -o root -g root -m 0644 "$BUNDLE_DIR/media-balancer.service" /etc/systemd/system/media-balancer.service
install -o root -g root -m 0644 "$BUNDLE_DIR/media-balancer.timer" /etc/systemd/system/media-balancer.timer
modprobe tcp_bbr
sysctl net.ipv4.tcp_available_congestion_control | grep -qw bbr || { echo "El kernel no ofrece BBR"; exit 1; }
printf 'tcp_bbr\n' > /etc/modules-load.d/orionx-bbr.conf
[[ -f /etc/sysctl.d/99-orionx-performance.conf ]] && cp -a /etc/sysctl.d/99-orionx-performance.conf "/etc/sysctl.d/99-orionx-performance.conf.bak.$(date +%s)"
install -o root -g root -m 0644 "$BUNDLE_DIR/orionx-performance.conf" /etc/sysctl.d/99-orionx-performance.conf
sysctl -p /etc/sysctl.d/99-orionx-performance.conf
[[ "$(sysctl -n net.ipv4.tcp_congestion_control)" == bbr ]] || { echo "No se pudo activar BBR"; exit 1; }
[[ "$(sysctl -n net.core.default_qdisc)" == fq ]] || { echo "No se pudo activar FQ"; exit 1; }
sed "s/__PHP_VERSION__/${PHP_VERSION}/g" "$BUNDLE_DIR/nginx-balancer.conf" > /etc/nginx/sites-available/media-balancer
chown root:root /etc/nginx/sites-available/media-balancer; chmod 0644 /etc/nginx/sites-available/media-balancer
install -d -o root -g www-data -m 0750 /srv/orionx/media
touch /etc/nginx/orionx-rtmp.conf; chmod 0640 /etc/nginx/orionx-rtmp.conf
grep -qF 'include /etc/nginx/orionx-rtmp.conf;' /etc/nginx/nginx.conf || sed -i '/^[[:space:]]*http[[:space:]]*{/i include /etc/nginx/orionx-rtmp.conf;' /etc/nginx/nginx.conf
ln -sfn /etc/nginx/sites-available/media-balancer /etc/nginx/sites-enabled/media-balancer; rm -f /etc/nginx/sites-enabled/default
nginx -t; systemctl daemon-reload; systemctl enable --now nginx redis-server "php${PHP_VERSION}-fpm" media-balancer.timer; systemctl reload nginx; systemctl start media-balancer.service
echo "BALANCER_INSTALL_OK php=${PHP_VERSION} ubuntu=${VERSION_ID}"
BASH;}
}
