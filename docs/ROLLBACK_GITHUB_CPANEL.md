# Rollback GitHub + cPanel

## Principios

El rollback debe preservar datos operativos y auditoria. No usar `migrate:fresh`, `db:wipe`, `git reset --hard` ni `rm -rf` en produccion.

Antes de cada despliegue deben existir:

- Respaldo de base de datos MySQL.
- Copia del `.env` del servidor.
- Copia de `storage/app/private`.
- Identificador del commit desplegado.
- Registro de la hora y del responsable.

## Rollback de codigo

1. Aprobar una ventana de mantenimiento.
2. Activar mantenimiento:

```bash
cd /home/jesusvalera/ops-biomed
php artisan down --render=errors::503 --retry=60
```

3. Restaurar el paquete o release anterior aprobado. No borrar el directorio completo; reemplazar solo los archivos del release.
4. Mantener el `.env`, `storage/app/private`, logs y backups del servidor.
5. Ejecutar:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan up
```

6. Verificar `/up`, login, dashboard, documentos privados, colas y scheduler.

## Rollback de migraciones

No ejecutar `migrate:rollback` automaticamente. Revisar primero la migracion y el impacto en datos. Si la version anterior es compatible con el esquema nuevo, restaurar solo codigo. Si no es compatible:

1. Detener el despliegue.
2. Respaldar la base actual.
3. Revisar el `down()` de la migracion con el responsable tecnico.
4. Restaurar el respaldo de base aprobado solo con autorizacion.
5. Ejecutar `php artisan migrate:status` y validaciones funcionales.

Nunca usar `migrate:fresh` o `db:wipe` para corregir una falla de produccion.

## Rollback de archivos privados

Restaurar la copia aprobada de `storage/app/private` en su ubicacion original, conservar permisos y no publicarla dentro de `public/`.

## Recuperacion de mantenimiento

Si una orden falla durante mantenimiento:

```bash
cd /home/jesusvalera/ops-biomed
php artisan up
```

Luego revisar `storage/logs/laravel.log`, el estado de la base y el ultimo commit. El script `scripts/deploy-cpanel.sh` incluye un `trap` que intenta salir de mantenimiento cuando una orden falla.

## Criterio de recuperacion

La aplicacion vuelve a servicio solo cuando:

- `/up` responde HTTP 200.
- Login y cierre de sesion funcionan.
- Dashboard y rutas criticas cargan sin 500.
- No existen jobs fallidos no evaluados.
- Scheduler y cola siguen configurados.
- Evidencias y documentos privados no se exponen.

Registrar el incidente, la causa, el commit restaurado y las acciones posteriores.
