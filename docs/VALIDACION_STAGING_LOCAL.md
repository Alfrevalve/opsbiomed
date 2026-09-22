# Validacion de staging local

Fecha de ejecucion: 2026-09-20. Alcance: Laragon en Windows, MySQL local y datos ficticios. No se contacto ningun dominio, servicio remoto ni entorno de produccion.

## Entornos

| Uso | Ruta / base | Evidencia |
|---|---|---|
| Instalacion limpia del commit versionado `10a43743` | `C:\laragon\www\ops-biomed-staging-local` / `ops_biomed_staging_local` | Composer limpio, package discovery, 36 migraciones, RoleAndPermissionSeeder, PilotDemoSeeder y Vite. |
| Codigo de trabajo actual | `C:\laragon\www\ops-biomed` / `ops_biomed_staging_local` | 38 migraciones, ocho cuentas piloto y smoke HTTP por rol. |
| Recuperacion aislada | `C:\laragon\www\ops-biomed-staging-local-restore-storage` / `ops_biomed_restore_test` | 36 migraciones, centinelas, checksums y smoke HTTP autenticado. |

Las unicas bases modificadas destructivamente por las pruebas fueron las autorizadas: `ops_biomed_staging_local` y `ops_biomed_restore_test`. La prueba MySQL usa `phpunit.mysql.xml` local ignorado por Git y limita `DatabaseTruncation` a `ops_biomed_staging_local` en `127.0.0.1:3306`.

## Instalacion limpia

- PHP 8.3.33; MySQL 8.4.3; Node 22.
- `composer install --no-interaction --prefer-dist --optimize-autoloader`: 125 paquetes; `artisan package:discover` correcto.
- `npm ci --no-audit --no-fund`: 94 paquetes; build correcto.
- `.env` aislado: `APP_ENV=staging`, `APP_DEBUG=false`, cache/sesion en archivos, correo `log`, DB local. El archivo no se incluyo en backups.
- La copia limpia corresponde al commit `10a43743` y solo contiene 36 migraciones. El arbol de trabajo posterior tiene dos migraciones adicionales de Fase 3 y cambios aun sin commit. No son el mismo artefacto desplegable.

## Validaciones finales

| Comando | Resultado |
|---|---|
| `composer validate --strict` | Correcto. |
| `composer audit --locked` | 0 advisories. |
| `php artisan test --compact` | 277 tests / 1,785 assertions; suite completa en el arbol actual. |
| `vendor/bin/phpunit --configuration phpunit.mysql.xml --no-progress` | 6 tests / 58 assertions; procesos MySQL independientes. |
| `npm audit` | 0 vulnerabilidades. |
| `npm run build` | Correcto; Vite 6.4.3. |
| `php artisan view:cache` | Correcto. |
| `php artisan route:list --except-vendor` | 148 rutas. |
| `php artisan migrate:status` | 38 migraciones `Ran` en staging actual; 36 en el snapshot limpio restaurado. |
| `vendor/bin/pint --dirty --format agent` | Correcto; aplico formato a archivos PHP modificados. |
| `git diff --check` | Sin errores. |

La suite Release-B, creada desde el commit limpio con un marcador local inocuo, paso 245 tests / 1,536 assertions. Sus pruebas no incluyen las dos migraciones que aun no estan en ese commit.

## Smoke HTTP restaurado y piloto por roles

En la base restaurada, `/up`, `/login`, `/dashboard/ops`, `/cases`, `/inventory` y `/documents` respondieron 200 tras autenticacion; dashboard sin autenticacion redirigio a login. En staging actual se recorrieron URLs GET con los ocho usuarios de `PilotDemoSeeder`: las rutas permitidas devolvieron 200 y las denegadas 403; no hubo 500.

| Rol | Resultado relevante |
|---|---|
| Administrador | Dashboard, solicitudes, inventario, forecast, billing y usuarios: 200. |
| Jefe de Linea | Operacion/reportes: 200; usuarios: 403. |
| Direccion Tecnica | Fallas, devoluciones, inventario: 200; billing: 403. |
| Almacen | Inventario, devoluciones, solicitudes: 200; billing: 403. |
| Instrumentista | Solicitudes/devoluciones: 200; billing: 403. |
| Comercial | Reporte comercial, billing de estado y documentos: 200; forecast: 403; importes financieros ocultos. |
| Cobranza | Billing y reporte de billing: 200; forecast: 403. |
| Gerencia | Billing, reportes y forecast: 200. |

La inspeccion de privacidad encontro una posible lectura/carga de evidencia de factura desde pantallas documentales generales. Se cerraron consultas, policies, carga, validacion, descarga y relaciones embebidas; `DocumentEvidenceTest` quedo en 12 tests / 58 assertions y cubre IDOR, listados, pantalla de caso, facturacion e importes ocultos. Es una correccion local, no una certificacion de seguridad.

## Limites y decision

- El recorrido de roles fue de lectura/autorizacion. No se completo el ciclo quirurgico completo con cambios de estado, consumo, devolucion, conciliacion y pago.
- El smoke cronometrado uso `artisan serve`, no Apache/Laragon con configuracion de hosting; no se extrapolan tiempos.
- El RTO observado de rollback local fue 12.864 s, con selector de archivo y dos servidores locales. El tiempo exacto de restauracion de DB/storage no quedo asentado de forma limpia en el manifiesto; repetir para fijar RTO formal.
- Sin tarea permanente de Windows. `schedule:run` se ejecuto y no encontro tarea vencida; el comando SLA se ejecuto dos veces manualmente: 4 alertas en la primera, 0 adicionales en la segunda, 0 jobs y 0 failed jobs.
- Estado de la fase: **NO-GO LOCAL** hasta versionar Fase 3, repetir backup/restore con duracion, y completar el flujo seco integral.
- Esto no autoriza despliegue remoto ni representa `GO PRODUCCION`.
