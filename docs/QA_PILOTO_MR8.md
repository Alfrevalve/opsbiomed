# QA piloto OPS BIOMED MR8

Fecha de ejecucion: 15/09/2026
Ambiente: Laragon, `http://ops-biomed.test`
Base: escenario idempotente de `PilotDemoSeeder`

## Resultado ejecutivo

La validacion automatizada del piloto paso correctamente:

- 8 usuarios piloto pueden autenticarse y abrir el dashboard.
- El administrador abrio las rutas operativas, inventario, alertas, facturacion, reportes, manual, maestros y administracion sin errores 403, 404 o 500.
- Las restricciones principales por rol fueron verificadas.
- Los usuarios inactivos quedan bloqueados por `EnsureUserIsActive`.
- La lectura comercial de facturacion no muestra montos internos sin `billing.view`.
- El evaluador de SLA ejecuto correctamente y genero alertas.
- No se eliminaron datos ni se ejecutaron comandos destructivos.

## Usuarios y roles verificados

| Usuario | Rol | Resultado funcional |
| --- | --- | --- |
| `admin@ops.test` | Administrador | Dashboard y gestion completa verificados |
| `jefe.linea@ops.test` | Jefe de Linea | Agenda, cobertura, forecast y aprobaciones verificadas |
| `dt@ops.test` | Direccion Tecnica | Fallas, devoluciones y revision tecnica verificadas |
| `almacen@ops.test` | Almacen | Inventario, devoluciones y trazabilidad verificadas |
| `instrumentista@ops.test` | Instrumentista | Trazabilidad y operacion de caso verificadas |
| `comercial@ops.test` | Comercial | Reporte comercial y acceso limitado verificados |
| `cobranza@ops.test` | Cobranza | Facturacion y reporte financiero verificados |
| `gerencia@ops.test` | Gerencia | Facturacion, reportes y aprobaciones verificadas |

Las contrasenas temporales no se documentan en este reporte.

## Flujos aprobados

- Inicio de sesion y redireccion al dashboard para los ocho usuarios.
- Dashboard operativo con datos piloto.
- Solicitudes y caso `MR8-PILOT-001`.
- Control operativo del caso.
- Agenda y conflictos.
- Inventario, cobertura MR8 y forecast.
- Fallas tecnicas y lote bloqueado.
- Devolucion pendiente de inspeccion.
- Documentos y evidencia de solicitud.
- Facturacion pendiente de OC.
- Manual operativo y acceso administrativo restringido.
- Maestros de instituciones, medicos y precios.
- Perfil de usuario.
- Evaluacion y listado de alertas SLA.
- Bloqueo de usuario inactivo.
- Restriccion de Forecast para Comercial.
- Restriccion de facturacion para Almacen e Instrumentista.
- Restriccion de administracion de usuarios para roles no administradores.

## Datos piloto comprobados

- Caso principal: `MR8-PILOT-001`.
- Caso administrativo: `MR8-PILOT-CIERRE-001`.
- Reservas activas asociadas al caso principal.
- Inventario verde: `PILOT-VERDE-9-3`.
- Inventario amarillo: `PILOT-AMARILLO-9-3-D`.
- Inventario rojo: `PILOT-ROJO-10-2`.
- Respaldo YSAN: `PILOT-YSAN-9-3`.
- Lote vencido: `PILOT-VENCIDO-10-2`.
- Lote bloqueado: `PILOT-BLOQUEADO-10-3-D`.
- Lotes en cuarentena: `PILOT-DEVOLUCION-CUARENTENA` y `PILOT-CUARENTENA-F3`.
- Lote desvalorizado: `PILOT-DESVALORIZADO-10-3`.
- Falla tecnica bloqueada asociada al caso principal.
- Devolucion en `pendiente_inspeccion`.
- Documento de solicitud cargado y pendiente de validacion operativa.
- Cobranza en estado `pendiente_oc`.
- Costo cero pendiente de aprobacion en el caso de cierre.

## Matriz de permisos

- Administrador: acceso completo confirmado.
- Direccion Tecnica: fallas y devoluciones confirmadas; Forecast permitido.
- Jefe de Linea: cobertura, Forecast, agenda y aprobaciones confirmados.
- Almacen: inventario, devoluciones y trazabilidad confirmados; facturacion bloqueada.
- Instrumentista: trazabilidad y operacion del caso confirmadas; facturacion bloqueada.
- Comercial: reportes comerciales y lectura comercial confirmados; Forecast y administracion de usuarios bloqueados.
- Cobranza: facturacion y reporte de cobranza confirmados.
- Gerencia: facturacion, reportes y aprobaciones confirmados.

## Rutas verificadas

Las rutas protegidas principales fueron ejercitadas mediante pruebas de feature. Las rutas revisadas incluyen:

`/dashboard/ops`, `/cases`, `/cases/{case}`, `/cases/{case}/control`, `/schedule`, `/schedule/conflicts`, `/inventory`, `/inventory/coverage`, `/inventory/forecast`, `/catalog/imports`, `/failures`, `/returns`, `/documents`, `/billing`, `/approvals/cost-zero`, `/reports`, `/reports/operations`, `/reports/commercial`, `/reports/billing`, `/reports/inventory`, `/manual`, `/trace`, `/profile`, `/masters/institutions`, `/masters/doctors`, `/masters/prices`, `/admin/users` y `/admin/manual`.

El listado final contiene 146 rutas de aplicacion y las 14 rutas del administrador del manual estan registradas.

## QA visual

Se dejaron como referencias de inspeccion:

- `/login`
- `/dashboard/ops`
- `/cases`
- `/schedule`
- `/inventory`
- `/inventory/coverage`
- `/inventory/forecast`
- `/alerts`
- `/failures`
- `/returns`
- `/documents`
- `/reports`
- `/manual`
- `/trace`
- `/profile`

La inspeccion visual responsive fue validada manualmente sin detectar problemas visuales ni de adaptacion en las cuatro resoluciones definidas. Se revisaron sidebar expandido y colapsado, menu movil, tablas, alertas, formularios, foco visible y ausencia de desplazamiento horizontal.

## Incidencias

### QA-ENV-001

- Fecha: 15/09/2026
- Usuario o rol: QA de ambiente
- Modulo: QA visual
- Ruta: todas las rutas visuales indicadas
- Descripcion: validacion visual responsive de las pantallas principales de OPS BIOMED MR8.
- Pasos: revisar la aplicacion en escritorio, laptop, tablet y movil; comprobar navegacion, tablas, formularios, alertas y sidebar.
- Resultado esperado: sin problemas visuales ni responsive.
- Resultado obtenido: sin problemas visuales ni responsive.
- Resoluciones revisadas: 1440 x 900, 1280 x 800, 768 x 1024 y 390 x 844.
- Severidad: Media
- Captura o referencia: rutas visuales listadas en este reporte y estructura `storage/app/qa-pilot/`.
- Estado: Validada manualmente

### QA-DATA-001

- Fecha: 15/09/2026
- Usuario o rol: Administrador
- Modulo: Documentos
- Ruta: `/documents`
- Descripcion: el documento piloto se guarda como `cargado`; la interfaz y el dashboard lo tratan como pendiente de validacion.
- Resultado esperado: evidencia visible como pendiente de validacion.
- Resultado obtenido: comportamiento correcto mediante el estado `cargado`.
- Severidad: Baja
- Captura o referencia: `/documents` y `MR8-PILOT-001`.
- Estado: Validada

No se detectaron incidencias criticas ni altas durante el smoke automatizado.

## Comandos ejecutados

- `php artisan migrate --force`
- `php artisan db:seed --class=RoleAndPermissionSeeder --force`
- `php artisan db:seed --class=PilotDemoSeeder --force`
- `php artisan optimize:clear`
- `php artisan test --compact`
- `npm run build`
- `php artisan view:cache`
- `php artisan route:list --except-vendor`
- `php artisan schedule:list`
- `php artisan ops:alerts:evaluate`

Resultados:

- Suite previa: 236 tests passed, 1,385 assertions.
- QA piloto: 8 tests passed, 141 assertions.
- Build Vite: correcto.
- Cache de vistas: correcto.
- Alertas: 8 SLA evaluados, 4 alertas creadas, 3 vencidas y 4 notificaciones internas.
- Scheduler: `ops:alerts:evaluate` programado cada hora.

## Recomendacion antes de produccion

Cambiar las contrasenas temporales antes de cualquier uso compartido, configurar HTTPS en el entorno objetivo, revisar almacenamiento privado y correo, y ejecutar una segunda ronda con datos anonimizados antes de habilitar usuarios reales.

## QA Fase 4 - validacion local y piloto seco

Fecha: 20/09/2026. Evidencia extendida en `VALIDACION_STAGING_LOCAL.md`, `PRUEBA_BACKUP_RESTAURACION_LOCAL.md`, `PRUEBA_ROLLBACK_LOCAL.md`, `PILOTO_SECO_ROLES.md`, `PRUEBAS_SEGURIDAD_LOCAL.md`, `RENDIMIENTO_LINEA_BASE.md`, `REVISION_PRIVACIDAD.md` y `ACTA_GO_NO_GO_LOCAL.md`.

- Backup sintetico verificado y restaurado en `ops_biomed_restore_test`; smoke autenticado de dashboard, casos, inventario y documentos: HTTP 200.
- Las ocho cuentas piloto pasaron smoke de permisos; Comercial no ve montos ni evidencias financieras y no accede a Forecast interno.
- Suite actual: 277 tests / 1,785 assertions; MySQL concurrente: 6 / 58. Composer audit y npm audit sin advisories.
- RELEASE-A/B y rollback se probaron en directorios locales aislados; no en Apache ni servidor remoto.
- No se completo el flujo transaccional de cirugia; la restauracion requiere medicion RTO limpia y el commit actual no incluye las dos migraciones Fase 3 del arbol de trabajo.
- Decision Fase 4: **NO-GO LOCAL para piloto operativo completo**. No implica GO de produccion. No hubo contacto con hosting ni datos reales.
