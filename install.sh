#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

[[ $EUID -eq 0 ]] || { echo "ERROR: ejecuta como root: sudo bash install.sh"; exit 1; }
[[ -r /etc/os-release ]] || { echo "ERROR: no se pudo detectar Ubuntu."; exit 1; }
source /etc/os-release
[[ "${ID:-}" == ubuntu ]] || { echo "ERROR: OrionX requiere Ubuntu Server."; exit 1; }
dpkg --compare-versions "${VERSION_ID:-0}" ge 24.04 || { echo "ERROR: OrionX requiere Ubuntu 24.04 o posterior."; exit 1; }

REPOSITORY=machetedj/OrionX
GITHUB_URL=https://github.com
API_URL=https://api.github.com/repos/${REPOSITORY}
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l

echo "============================================================"
echo "                 ORIONX INSTALLER"
echo "============================================================"
echo "Repositorio: ${GITHUB_URL}/${REPOSITORY}"
echo "Buscando la última versión disponible..."

apt-get update
apt-get install -y ca-certificates curl tar

release_json="$(curl --proto '=https' --tlsv1.2 --fail --silent --show-error --location --header 'Accept: application/vnd.github+json' "$API_URL/releases/latest" 2>/dev/null || true)"
tag="$(printf '%s' "$release_json" | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)"
if [[ -n "$tag" && "$tag" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  archive_url="$GITHUB_URL/$REPOSITORY/archive/refs/tags/$tag.tar.gz"
  selected_version="$tag"
else
  archive_url="$GITHUB_URL/$REPOSITORY/archive/refs/heads/main.tar.gz"
  selected_version=main
fi

work_dir="$(mktemp -d)"
cleanup(){ rm -rf "$work_dir"; }
trap cleanup EXIT
archive="$work_dir/orionx.tar.gz"
curl --proto '=https' --tlsv1.2 --fail --silent --show-error --location "$archive_url" --output "$archive"
tar -tzf "$archive" >/dev/null
tar -xzf "$archive" -C "$work_dir"
source_dir="$(find "$work_dir" -mindepth 1 -maxdepth 1 -type d -name 'OrionX-*' -print -quit)"
[[ -n "$source_dir" && -f "$source_dir/scripts/install-main.sh" ]] || { echo "ERROR: el paquete OrionX descargado no contiene el instalador interno."; exit 1; }

echo "Versión seleccionada: ${selected_version}"
echo "Descarga verificada. Iniciando instalación..."
/bin/bash "$source_dir/scripts/install-main.sh"
echo "OrionX ${selected_version} se instaló correctamente."
