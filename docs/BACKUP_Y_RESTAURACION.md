# Backup y restauracion

**Politica aprobada:** RPO maximo de 1 hora; RTO maximo de 2 horas. **Estado operativo:** aun no verificado en el cPanel contratado. No se accedio a produccion.

## Politica minima

- Base de datos antes de cada despliegue y backup automatizado al menos cada hora.
- Backup diario completo de base de datos, `.env` y evidencias/documentos privados.
- Retencion: 30 backups diarios, 12 semanales y 12 mensuales.
- Cifrado con clave publica aprobada; la clave privada de restauracion queda custodiada fuera del servidor.
- Almacenamiento fuera del document root y, cuando el hosting lo permita, copia secundaria fuera del mismo servidor.
- Registrar fecha, identificador, tamanio, checksum, resultado y responsable sin registrar secretos ni datos de salud.
- Prueba trimestral de restauracion. Un backup no es valido hasta restaurar y verificar datos y archivos.
- No borrar backups automaticamente en esta fase. La retencion debe probarse antes de automatizar limpieza.

## Capacidades cPanel pendientes de confirmar

No se tiene acceso a la cuenta cPanel ni a una instancia staging. Por tanto, no se afirma que el plan contratado permita: cron cada minuto, shell/SSH, `mysqldump`, GPG, symlinks seguidos por LiteSpeed, PHP CLI 8.3, espacio suficiente, backups fuera del servidor o cambiar el document root. El proveedor/administrador debe confirmarlo antes del piloto.

La alternativa si no hay symlinks es preparar una segunda carpeta completa y cambiar el document root mediante cPanel, conservando la carpeta anterior. Si tampoco se permite cambiarlo de forma controlada, el despliegue se detiene y se solicita una funcion equivalente al proveedor.

## Preparacion de backup en staging

`scripts/backup-cpanel.sh` opera en `--dry-run` por defecto. Su modo `--execute-staging` exige `CPANEL_TARGET_ENV=staging`, rutas de aplicacion y backup identicas a sus valores esperados, nombre y host de base staging exactos, archivo de cliente MySQL con modo `0600`, destinatario GPG y storage compartido privado. Las rutas deben estar fuera de `public_html`; el destino debe estar fuera del arbol de la aplicacion. El script cifra el dump y `.env` mas evidencias privadas, genera `SHA256SUMS` y manifiesto. No implementa copia offsite ni borrado por retencion.

Ejemplo de ensayo, con valores provisionados en el host staging y sin pasarlos por argumentos visibles:

```bash
bash scripts/backup-cpanel.sh --dry-run
bash scripts/backup-cpanel.sh --execute-staging
```

El manifiesto inicial indica `restore_verified=false`. Tras restaurar y revisar el backup en una base y storage aislados, el responsable registra el resultado de restauracion, fecha, tamanio, checksum y aprobacion fuera de Git. No cambiar el manifiesto automaticamente a verificado solo por haber terminado el dump.

## Restauracion

1. Abrir incidente, registrar responsable, hora y ventana; detener escrituras solo con autorizacion.
2. Seleccionar backup y comprobar el checksum antes de descifrar.
3. Restaurar primero en staging aislado. Nunca probar sobre produccion.
4. Restaurar DB y evidencias al mismo punto temporal; verificar tablas, migraciones, relaciones, conteos, descargas privadas y health check.
5. Ejecutar pruebas funcionales con datos ficticios y comprobar permisos; registrar duracion real contra RTO de 2 horas.
6. La restauracion productiva requiere aprobacion explicita del propietario. Registrar escrituras que podrian perderse y no reabrir el servicio hasta conciliar.

No usar `migrate:rollback` como estrategia de recuperacion. Restaurar base y storage es una decision de recuperacion con posible perdida de operaciones posteriores al punto de respaldo.

## Evidencia de esta fase

La suite de concurrencia utiliza solamente `ops_biomed_test`; no constituye una prueba de backup/restauracion. A la fecha de este documento no existe un backup cPanel ni una restauracion cPanel ensayada, por lo que continuidad queda **NO VERIFICADA** y el piloto sigue **NO-GO**.
