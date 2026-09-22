# Prueba de backup y restauracion local

Fecha: 2026-09-20. Solo MySQL local en `President-lapto`; ningun dato real ni archivo `.env`.

## Artefacto

- ID: `20260920T214037372Z`.
- Directorio externo al proyecto: `C:\laragon\www\ops-biomed-staging-local-backups\20260920T214037372Z`.
- Base de origen: `ops_biomed_staging_local`; restauracion exclusivamente en `ops_biomed_restore_test`.
- Commit declarado: `10a43743`; esquema: 36 migraciones.
- Dump SQL: 125,202 bytes. ZIP de storage privado ficticio: 294 bytes. El backup contiene SQL, ZIP, manifiesto y checksums; `.env` ausente.
- Duracion de creacion registrada: 1.2 s.
- SHA-256 SQL: `dc5dd926435caa4a89bc47a7d39d0756ca28ba34c948d4d8ad47547ca7d96661`.
- SHA-256 ZIP privado: `9d4fa7fded81934170aba4e2e8a036d8aad5101f94d99da72f94c2117c065fc5`.

## Verificacion de recuperacion

El script `scripts/restore-local-staging.ps1` verifico checksums, host y destino antes de restaurar. Un primer intento importo DB y archivo, pero la verificacion final fallo por quoting SQL de PowerShell; corregido el comando, `-VerifyOnly` confirmo:

`migraciones/usuarios/productos/lotes/reservas/documentos/fallas/devoluciones/centinela-cache = 36|8|9|14|3|1|1|1|1`.

El archivo privado centinela coincide con SHA-256 `88067bebfd5db50402a74704394d24551632ced9c10425b09cf1f77ad8ddcadb`. El centinela DB es `FASE4-SYNTHETIC-DB-ROW-20260920`. En el servidor aislado, login autenticado, dashboard, solicitudes, inventario y documentos devolvieron HTTP 200.

## RPO/RTO

- Objetivos operativos pendientes de aprobacion formal: RPO <= 1 h y RTO <= 2 h.
- El manifiesto fecha backup a las 21:40:38Z y validacion a las 21:42:52Z, pero esa ventana incluye coordinacion/diagnostico y no constituye por si sola RTO de recuperacion.
- El tiempo exacto de la fase de importacion DB + expansion storage no quedo persistido por el fallo inicial de verificacion. Repetir la restauracion desde cero en el mismo destino autorizado con el script corregido y registrar `restore_duration_seconds` antes de aceptar RTO.
- No extrapolar esta medicion local a un hosting ni considerar probado el RPO por el tamano del archivo.

## Repeticion segura

```powershell
.\scripts\backup-local-staging.ps1
.\scripts\restore-local-staging.ps1 -BackupDirectory 'C:\laragon\www\ops-biomed-staging-local-backups\<ID>'
```

El restaurador rechaza destinos no vacios. Solo realizar cualquier reinicio en `ops_biomed_restore_test`, tras verificar identidad y contenido ficticio; no cambiar `.env` del proyecto principal ni usar `migrate:fresh` sobre otra base.
