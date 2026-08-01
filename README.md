# OrionX

Panel modular para administrar contenido propio o debidamente licenciado, servidores principales y balanceadores.

## Instalación automática

En Ubuntu Server 24.04 o posterior:

```bash
curl -fsSL https://raw.githubusercontent.com/machetedj/OrionX/main/install.sh -o install.sh
sudo bash install.sh
```

El bootstrap busca la última Release estable de `machetedj/OrionX`. Si todavía no existen Releases, instala la rama `main`. El instalador detecta la IP, instala MariaDB local, PHP, Nginx, Redis, FFmpeg firmado, workers, firewall y TLS temporal; finalmente muestra las credenciales administrativas una sola vez.

Los balanceadores se registran, instalan, sincronizan y actualizan únicamente desde el dashboard mediante SSH.

## Desarrollo

PHP 8.5, MVC ligero, PDO, MariaDB/MySQL, Redis, Nginx, Tailwind CSS, Alpine.js y HTMX. Copia `.env.example` a `.env`, instala Composer y ejecuta `composer test`.

No publiques `.env`, credenciales, claves privadas, backups ni contenido multimedia. No expongas MariaDB públicamente.
