# Línea base y avance de la Fase 1

## Estado inicial encontrado

- Proyecto PHP 8.3 con MVC ligero y esquema inicial MariaDB/MySQL.
- Repositorio todavía sin primer commit; todos los archivos aparecen como nuevos.
- Composer 2.10.2 y las dependencias quedaron instalados; `composer.lock` fija las versiones reproducibles.
- No existe una base XUI One configurada en este entorno para realizar el inventario de la Fase 0.
- El ejecutor anterior no bloqueaba migraciones simultáneas ni detectaba cambios posteriores en archivos ya aplicados.
- La aplicación no impedía arrancar producción con `APP_KEY` inválida, depuración activa o cookies inseguras.

## Cambios de fundamentos

- Validación central de variables críticas y postura segura de producción.
- Ejecutor incremental con bloqueo de base y checksum SHA-256 por migración.
- Compatibilidad con instalaciones que ya tengan `schema_migrations` sin checksum.
- Comando de verificación de PHP, extensiones, directorios, base, esquema y Redis.
- Pruebas unitarias para configuraciones de producción válidas e inseguras.

## Comandos de aceptación

```bash
composer install
php scripts/migrate.php
composer test
composer verify
php scripts/migrate.php
```

La segunda ejecución de migraciones debe informar que no hay cambios. Modificar una migración ya aplicada debe producir un error de checksum; cualquier cambio nuevo debe añadirse en otro archivo.

## Pendiente para cerrar la Fase 0

Se necesita acceso de solo lectura a una restauración o réplica de la base XUI One. El inventario debe obtener versión, motor, tablas, vistas, tamaños, conteos, columnas, claves, codificación y una muestra anonimizada de valores problemáticos. No se requieren credenciales de producción dentro del repositorio.

## Pendiente para cerrar la Fase 1

- Repetir las pruebas ya aprobadas en Windows sobre Ubuntu 22.04/24.04.
- Probar instalación limpia y actualización repetida contra MariaDB real.
- Probar respaldo y restauración.
- Añadir el flujo de integración continua cuando se defina el repositorio remoto.
- Documentar RPO, RTO y responsables operativos.
