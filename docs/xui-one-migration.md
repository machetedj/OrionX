# Migración desde XUI One

El importador crea una réplica aislada de las tablas de XUI One dentro de la base del panel. Conserva nombres de columnas, tipos, IDs, índices, relaciones y filas, pero antepone `xui_legacy__` para evitar colisiones con las tablas nativas.

## Preparación

1. Haz un respaldo consistente de XUI One y prueba su restauración.
2. Crea un usuario MySQL de solo lectura para la base XUI.
3. Completa las variables `XUI_DB_*` en `.env`.
4. Ejecuta primero `php scripts/migrate.php`.

## Auditoría sin cambios

```bash
php scripts/xui-import.php
```

Muestra todas las tablas, filas y nombres de destino. No escribe nada.

## Importación

```bash
php scripts/xui-import.php --execute
```

Si ya existe una réplica con el mismo prefijo, el proceso se detiene. `--replace` permite sustituirla y debe usarse únicamente después de verificar el respaldo:

```bash
php scripts/xui-import.php --execute --replace
```

Cada ejecución queda registrada en `xui_imports`; el detalle, conteo y checksum por tabla queda en `xui_import_tables`.

## Alcance

Esta etapa conserva íntegramente la base para poder reconciliar variantes del esquema de XUI One. No activa automáticamente cuentas, streams ni servidores en producción. La segunda etapa convierte cada entidad al esquema nativo, genera un reporte de diferencias y solo después permite el cambio de operación.
