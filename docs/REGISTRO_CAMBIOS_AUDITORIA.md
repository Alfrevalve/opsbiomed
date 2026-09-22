# Registro de cambios de auditoria

## 2026-09-20 - Fase 3 de fortalecimiento

- Se agrego `reservations.release` y se asigno unicamente a Administrador, Jefe de Linea y Almacen.
- Se implemento liberacion total con motivo, elegibilidad basada en estados/evidencias existentes, locks, transaccion, idempotencia y auditoria `reservation.released`.
- Se agrego cancelacion de caso con preview, checkbox de confirmacion y motivo; requiere que todas las reservas activas sean elegibles o no modifica ningun estado. Audita `case.cancelled_with_reservations`.
- No se suma cantidad al stock fisico; la reserva cambia estado y permanece en historial.
- Si la transaccion de un ajuste falla despues de guardar un archivo, se elimina solo ese archivo nuevo. Un test fuerza fallo de auditoria y revisa rollback SQL/storage.
- Harness MySQL con procesos independientes: 6 pruebas / 58 aserciones aprobadas en `ops_biomed_test`, MySQL 8.4.3, `REPEATABLE-READ`.
- Validacion general: 275 tests / 1,768 assertions; Composer validate/audit, npm audit, Vite build, Blade, rutas, scheduler, migrate status y Pint correctos. MySQL de prueba: 38 migraciones aplicadas y 52 tablas.
- El commit de catalogo reintenta hasta tres veces ante deadlocks; quedan tests concurrentes para import, reserva activa, agenda y ajustes.
- Laravel se limita a `^12.0`; Composer actualizo el lock content hash sin actualizar dependencias.
- Workflow remoto queda desactivado. Se prepararon scripts staging, dry-run por defecto, exactitud de rutas, checksum y backup cifrado.
- Scheduler declara zona `America/Lima` y evita solapamientos; se agrego test de configuracion. No se configuro cron productivo.
- Documentacion actualizada: auditoria, matriz de riesgos, plan, continuidad, respuesta a incidentes y despliegue.

## Validaciones anteriores

Las fases anteriores documentaron las correcciones de autorizacion documental, enlaces HTTP/HTTPS, CSV, registro publico, reservas, catalogo, agenda, cierre, billing y devoluciones. Consultar `AUDITORIA_TECNICA_INTEGRAL.md` para el estado consolidado. Los datos de pacientes, claves y contenido de `.env` se excluyen de este registro.

## Limite de autorizacion

No hubo acceso, migraciones, pruebas ni operaciones sobre produccion. La referencia a `ops_biomed_test` corresponde solo a la prueba historica de Fase 3. En Fase 4 las operaciones destructivas se limitaron a `ops_biomed_staging_local` y `ops_biomed_restore_test`. Staging cPanel y restore/rollback de hosting siguen pendientes.

## 2026-09-20 - QA Fase 4 local

- Se creo staging aislado desde `git archive HEAD` en `C:\laragon\www\ops-biomed-staging-local`, con `APP_ENV=staging`, `APP_DEBUG=false`, DB local y sin datos reales.
- Para cumplir el alcance autorizado de Fase 4, la suite MySQL concurrente se reconfiguro al unico destino de pruebas aprobado `ops_biomed_staging_local`; 6 tests / 58 assertions aprobadas. Nunca se usaron otras DB para operaciones destructivas en esta fase.
- Instalacion limpia del commit `10a43743`: Composer/package discovery y npm/Vite correctos; ese snapshot tiene 36 migraciones. El arbol actual tiene 38; dos migraciones/cambios de Fase 3 siguen sin commit. No existe aun un artefacto versionado unico para promover.
- Backup ficticio `20260920T214037372Z`, SQL 125,202 bytes, storage ZIP 294 bytes, checksums SHA-256 registrados, `.env` excluido. Restore en `ops_biomed_restore_test`; 36 migraciones, conteos y hash de centinela verificados; smoke autenticado HTTP 200.
- Se simularon RELEASE-A/B fuera del repo. RELEASE-B paso 245 tests / 1,536 assertions; health/login en 200. Rollback local al release A: 12.864 s. Selector local, no symlink/hosting.
- Suite del arbol actual: 277 tests / 1,785 assertions; Composer y npm audit sin advisories, build, cache Blade, 148 rutas y diff check correctos. Suite MySQL: 6/58.
- Smoke GET con ocho cuentas: accesos esperados 200/403 y sin 500. Se corrigio control de acceso a evidencias financieras: filtros, policies y requests ahora exigen permiso billing/aprobaciones; `DocumentEvidenceTest` 12 tests / 58 assertions.
- Scheduler: `schedule:run` sin evento debido en ese minuto; `ops:alerts:evaluate` manual crea 4 alertas y una repeticion 0; `jobs=0`, `failed_jobs=0`. No se creo tarea Windows permanente ni se enviaron notificaciones externas.
- No se completo el flujo clinico/administrativo integral ni se midio limpiamente la duracion de importacion DB+storage. Decision: NO-GO LOCAL hasta cerrar versionado, RTO y piloto seco. No se accedio ni se modifico produccion.

## Aclaracion de base de pruebas

La anotacion historica de Fase 3 que refiere a `ops_biomed_test` pertenece a esa fase previa. Para Fase 4, los unicos destinos modificados destructivamente fueron `ops_biomed_staging_local` y `ops_biomed_restore_test`, respetando la autorizacion expresa vigente.

## 2026-09-21 - Release candidate local

- Se consolido el estado validado en el commit `3499aac7` (`chore: consolidate pilot hardening and reservation controls`).
- El snapshot limpio contiene 38 migraciones; Composer `package:discover`, `npm ci` y Vite terminaron correctamente.
- La suite del snapshot limpio paso 277 tests / 1,785 assertions en 68.29 s; `view:cache`, `migrate:status` y 148 rutas tambien pasaron.
- `REL-001` queda cerrado localmente. Sigue abierta la medicion de restauracion limpia con cronometro (`REC-002`) y el piloto seco mutante (`PILOT-001`); la decision permanece **NO-GO LOCAL**.
