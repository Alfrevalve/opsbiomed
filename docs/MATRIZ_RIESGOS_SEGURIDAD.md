# Matriz de riesgos tecnicos y de seguridad

Estado actualizado: 2026-09-21. Es evidencia de QA local, no certificacion ni permiso para usar produccion.

| Codigo | Severidad / estado | Evidencia y riesgo | Mitigacion / pendiente |
|---|---|---|---|
| SEC-001 | P1 / corregido local | La carga documental valida el permiso del registro destino; previene escritura cruzada. | Pruebas Feature; repetir matriz de roles en staging. |
| SEC-002 | P1 / corregido local | Links externos en control operativo solo permiten HTTP/HTTPS. | Pruebas contra esquema javascript y URL HTTPS. |
| SEC-003 | P1 / corregido local | CSV neutraliza prefijos de formula de Excel. | Pruebas de forecast/reportes; no exponer precios/datos sensibles. |
| RES-001 | P1 / implementado local | Liberacion total de reservas y cancelacion atomica bajo permiso `reservations.release`; no altera stock fisico. | 14 Feature tests; requiere entrenamiento y validacion en staging. |
| DB-001 | P1 / verificado en QA MySQL | Concurrencia InnoDB real con procesos independientes. | 6 pruebas/58 aserciones sobre `ops_biomed_test`; sin doble reserva/asignacion ni lotes duplicados. No extrapolar a configuracion de hosting. |
| DB-002 | P1 / mitigado local | Deadlock transitorio al confirmar dos imports del catalogo. | Commit de catalogo reintenta hasta 3 transacciones; MySQL concurrente pasa. |
| DEP-001 | P1 / NO-GO | Produccion/cPanel no disponible para verificar releases, PHP, cron, symlinks, permisos o red. | Workflow remoto desactivado; ensayar release y rollback en staging antes de habilitar cualquier despliegue. |
| REC-001 | P1 / NO-GO | No hay backup/restore cPanel demostrado; RPO/RTO aprobados pero no medidos. | Backup cifrado y restauracion aislada obligatorios; RPO <= 1 h, RTO <= 2 h. |
| SCH-001 | P1 / NO-GO | Cron real no verificado; alertas SLA corren cada hora. | Tareas declaran America/Lima y `withoutOverlapping`; falta cron cPanel, prueba horaria y monitoreo. |
| FILE-001 | P2 / corregido local | Archivo privado podia quedar huerfano si fallaba la transaccion de ajuste. | Compensacion borra solo archivo nuevo; test fuerza error, comprueba rollback SQL y limpieza sin exponer rutas. |
| PRIV-001 | P2 / pendiente | Hosting, retencion, restore/offsite y controles finales de acceso a datos sensibles no validados. | Revisar con propietario/privacidad y probar con cuentas ficticias en staging. |
| WEB-001 | P2 / pendiente | Cabeceras HTTP/TLS finales de LiteSpeed no observadas. | Revisar CSP/HSTS/framing y respuestas del host staging. |
| COMP-001 | Resuelto local | Manifest y lock limitados a Laravel 12; sin actualizacion de paquetes. | Verificar `composer validate --strict` y `composer audit --locked`; Laravel 13 requiere proyecto separado. |
| CI-001 | P2 / verificado local | El workflow de produccion anterior hacia despliegue in-place con `tar --overwrite`. | Reemplazado localmente por job remoto `if: false`; workflow manual ejecuta solo calidad. |

## Decision de piloto

**NO-GO.** La logica de reservas y concurrencia pasa QA local, pero no existe evidencia cPanel de staging, backup cifrado/restaurado, rollback de release ni scheduler. No se accedio ni se modifico produccion. No hay claims de cumplimiento legal, certificacion o validacion clinica.

## Actualizacion QA Fase 4 local - 2026-09-20

| Codigo | Severidad / estado | Evidencia y riesgo | Mitigacion / pendiente |
|---|---|---|---|
| PRIV-002 | P1 / corregido en arbol actual | Comercial podia consultar/listar evidencias financieras de factura/OC asociadas al caso y forzar rutas de documento por ID. | Visibilidad filtrada por tipo y entidad, policy sobre ver/validar/borrar/descargar, autorizacion de carga; pruebas `DocumentEvidenceTest` 12/58. Revalidar al versionar/desplegar. |
| REL-001 | P1 / cerrado local | Commit `3499aac7` consolida codigo y migraciones Fase 3; snapshot limpio aplica 38 migraciones, instala Composer, ejecuta `npm ci`, compila y pasa 277/1,785 tests. | Mantener el commit como artefacto candidato; no promover sin staging remoto. |
| REC-002 | P1 / abierto | Restauracion de DB/storage verificada, pero el manifiesto no contiene un tiempo de importacion limpio tras un primer fallo del verificador PowerShell. | Repetir restore en base aprobada vacia, guardar `restore_duration_seconds` y medir recuperacion total/RPO. |
| PILOT-001 | P1 / abierto | Ocho cuentas pasaron smoke GET y permisos; no se ejecuto el flujo mutante completo del caso. | Piloto seco E2E con datos sinteticos y evidencia de estados/stock/auditoria antes de habilitar operacion. |
| SCH-002 | P2 / pendiente local | `schedule:run` fue invocado cuando no habia evento vencido; evaluator manual idempotente pasa, sin scheduler continuo configurado. | Observar ejecucion programada durante ventana de prueba y aprobar mecanismo local/hosting sin exponer servicios externos. |
| PERF-001 | P2 / linea base parcial | Ocho rutas medidas con `artisan serve`; no se midio lote de importacion 500 filas, cierre POST, memoria ni percentiles. | Ejecutar perfilado sobre hardware de staging y volumen representativo; no extrapolar tiempos locales. |

Decision de esta fase: **NO-GO LOCAL** para piloto completo. Continuan pendientes hosting real, seguridad/privacidad aprobada y toda validacion de produccion; no hubo acceso remoto.
