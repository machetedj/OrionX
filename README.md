# Licensed Media Panel — MVP

Panel modular para gestionar exclusivamente contenido propio o debidamente licenciado. No incluye mecanismos de evasión ni extracción no autorizada.

## Incluido

- PHP 8.5 recomendado, MVC ligero, router y contenedor de dependencias.
- PDO, autenticación Argon2id, sesiones seguras, CSRF y RBAC.
- Dashboard y gestión inicial de usuarios, revendedores, créditos, servidores y categorías.
- Libro contable de créditos transaccional.
- MariaDB/MySQL, Redis, Nginx e instalador idempotente.
- Logs rotativos y pruebas mínimas.

## Instalación Ubuntu 22.04/24.04

Ejecuta install.sh como root, edita /opt/licensed-media-panel/.env, después ejecuta scripts/migrate.php y scripts/create-admin.php.

## Desarrollo

Copia .env.example a .env, ejecuta composer install, composer test y sirve public/ con PHP o Nginx.

## Arquitectura

- app/Core: bootstrap, enrutamiento, vistas y CSRF.
- app/Controllers: adaptación HTTP, sin SQL.
- app/Repositories: acceso PDO.
- app/Services: reglas y transacciones.
- app/Security y app/Middleware: acceso y políticas.
- database/migrations: esquema versionado.

## Migración desde XUI One

El importador seguro replica primero todas las tablas e IDs de XUI One en un espacio aislado, con auditoría, conteos y checksums. Consulta `docs/xui-one-migration.md`. La activación de datos se realiza después mediante una conversión controlada al esquema nativo.

## Próximos módulos

Auditoría completa, API firmada para heartbeats, paquetes, workers Redis, catálogo, TMDB, EPG y entrega con URLs firmadas y X-Accel-Redirect.

No expongas MariaDB públicamente. Usa red privada, TLS, mínimo privilegio y firewall deny-by-default.
