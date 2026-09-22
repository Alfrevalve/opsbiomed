# Despliegue reversible OPS BIOMED

**Estado: diseno local preparado; no ensayado en cPanel ni ejecutado en produccion.** El workflow remoto queda desactivado expresamente. El piloto permanece NO-GO.

## Estructura objetivo

```text
/home/<cuenta>/opsbiomed/
  current -> releases/<sha>
  releases/<sha>/
  shared/.env
  shared/storage/
/home/<cuenta>/opsbiomed-backups/
```

La aplicacion y los backups deben estar fuera de `public_html`; el document root debe apuntar a `current/public`. Confirmar con el proveedor que LiteSpeed sigue symlinks y que PHP CLI 8.3, Composer y MySQL son compatibles. Ninguna ruta anterior es una ruta real del hosting hasta que el administrador la configure.

## Protecciones locales

- `.github/workflows/deploy-production.yml` solo se activa manualmente, ejecuta quality checks y tiene el job remoto condicionado permanentemente a `if: false`. No tiene SSH, dominio ni secretos de cPanel.
- `scripts/deploy-cpanel.sh` hace dry-run por defecto. El modo de ejecucion solo admite staging, compara literalmente `CPANEL_APP_PATH` con `EXPECTED_CPANEL_APP_PATH`, host DB y URL contra sus valores esperados, y valida `.env` (`APP_ENV`, URL, motor, nombre/host de DB y ausencia de `DB_URL`). Tambien exige ruta canonica, release anterior activo, checksum del artefacto, PHP 8.3, backup cifrado con checksum verificado, Composer, health check HTTPS y bloqueo `flock`.
- No se extrae con `tar --overwrite`; el artefacto se descomprime en un directorio de release nuevo. `.env` y `storage` son enlaces compartidos. Se aplican dependencias y migraciones antes de activar `current`.
- El cambio del symlink usa `mv -T` y el health check revierte el puntero al release previo si falla. El esquema no se revierte automaticamente; las migraciones deben ser expand/contract y compatibles con el codigo previo.
- Nunca se elimina un release durante el despliegue. Retener al menos tres releases; la limpieza se hara con procedimiento separado que compruebe rutas canonicas y nunca incluya `current`.
- No poner secretos, contenido de `.env`, tokens ni datos de pacientes en argumentos, manifiestos o logs.

El script no se ejecuto en un host cPanel. El unico modo verificado localmente es `--dry-run` con rutas de prueba, que no modifica filesystem, DB ni servicios.

## Secuencia de staging

1. Confirmar que el entorno sea staging y que ruta de aplicacion, base, respaldo, PHP, Composer y URL HTTPS coincidan con la configuracion aprobada.
2. Construir y revisar artefacto inmutable por SHA; instalar dependencias y compilar assets en CI. No incluir `.env`, documentos privados ni cache runtime.
3. Ejecutar backup cifrado de DB, `.env` y evidencia privada con `scripts/backup-cpanel.sh`; validar checksum y registrar operador. La restauracion de ese backup debe haberse probado previamente en staging aislado.
4. Crear una carpeta release nueva; enlazar `.env`/`storage`; crear carpetas Laravel; instalar dependencias que falten y revisar manifest Vite.
5. Verificar runtime, extensiones, `artisan about`, estado de migraciones y compatibilidad retroactiva. Aplicar `migrate --force` solamente tras aprobacion.
6. Calentar cache en la release nueva y activar atomicamente el symlink.
7. Consultar `/up`, verificar login de prueba y lectura operacional con cuenta ficticia. Probar alerta/SLA sin datos reales.
8. Ante fallo, restaurar el symlink previo, mantener la release fallida para analisis y registrar hora, SHA y resultado. No revertir esquema automaticamente.
9. Registrar resultado y conservar al menos tres releases y backups segun politica.

## Ensayo local de configuracion

```bash
CPANEL_TARGET_ENV=staging CPANEL_APP_PATH=/ruta/configurada EXPECTED_CPANEL_APP_PATH=/ruta/configurada bash scripts/deploy-cpanel.sh --dry-run
CPANEL_TARGET_ENV=staging CPANEL_APP_PATH=/ruta/configurada EXPECTED_CPANEL_APP_PATH=/ruta/configurada CPANEL_DB_NAME=opsbiomed_staging EXPECTED_CPANEL_DB_NAME=opsbiomed_staging CPANEL_DB_HOST=db-staging.example EXPECTED_CPANEL_DB_HOST=db-staging.example CPANEL_BACKUP_ROOT=/ruta/privada/backup EXPECTED_CPANEL_BACKUP_ROOT=/ruta/privada/backup bash scripts/backup-cpanel.sh --dry-run
```

Usar valores exactos solo dentro del staging provisionado. El ejemplo no ejecuta operaciones y no es una autorizacion para usar produccion. `--execute-staging` exige variables adicionales documentadas en `BACKUP_Y_RESTAURACION.md` y no puede operar en un entorno cuyo selector no sea `staging`.

## Checklist del scheduler en cPanel

- [ ] Confirmar PHP CLI 8.3 con ruta absoluta del hosting.
- [ ] Configurar cron cada minuto: `* * * * * /ruta/php83 /ruta/app/current/artisan schedule:run >> /ruta/privada/logs/scheduler.log 2>&1`.
- [ ] Mantener zona horaria `America/Lima`; las tareas Laravel declaran esa zona.
- [ ] Revisar permisos, rutas absolutas, disco disponible y rotacion/restriccion del log.
- [ ] Verificar `withoutOverlapping` y que el cache store soporte locks compartidos entre todas las instancias que ejecuten scheduler.
- [ ] Probar `php artisan schedule:list` y ejecutar manualmente `php artisan ops:alerts:evaluate` en staging; revisar exit code y log sin datos sensibles.
- [ ] Confirmar en `storage/logs/laravel.log` o log privado de cron la hora de inicio y resultado exitoso; definir alarma por ultima ejecucion ausente por mas de 70 minutos.
- [ ] Verificar que notificaciones son internas al sistema y contienen identificadores/modulo/prioridad, no nombre de paciente ni datos de salud. No activar canales externos sin autorizacion.
- [ ] Acordar responsable que revisa alertas horarias y politica de escalamiento.

No se configura el cron productivo en esta fase. `schedule:list` local no prueba que cPanel ejecute el cron.

## Brechas antes de produccion

Sin acceso al hosting no se pudo confirmar cron, symlinks, `mv -T`, `flock`, GPG, `mysqldump`, espacio, rutas o permisos. Tampoco se ha desplegado una release, ejecutado backup/restore o rollback en staging. Resolver y adjuntar evidencia antes de cambiar el job `if: false` o utilizar cualquier credencial remota.
