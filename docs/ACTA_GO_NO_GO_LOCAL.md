# Acta de decision local

Fecha: 2026-09-20 22:30 UTC. Ambiente evaluado: Laragon local aislado. Resultado: **NO-GO LOCAL para piloto operativo completo**.

## Semaforo

| Criterio | Estado | Evidencia / brecha |
|---|---|---|
| Instalacion limpia y build | PASS | Release A desde `10a43743`, Composer package discovery y Vite correctos. |
| Migraciones | PASS CON ALCANCE | 38 aplicadas a staging actual; snapshot limpio restaurado con 36. Codigo y esquema actual no coinciden por trabajo Fase 3 pendiente de commit. |
| Backup y checksum | PASS | Dump+ZIP ficticios, manifiesto, SHA-256 y `.env` excluido. |
| Restauracion y smoke | PASS | 36 migraciones, entidades/centinelas/hash y 4 rutas autenticadas HTTP 200. |
| RPO <= 1 h | NO DEMOSTRADO | Un snapshot local valido no prueba ventana real ni frecuencia/aprobacion. |
| RTO <= 2 h | PARCIAL | Rollback de selector local: 12.864 s; duracion limpia de restore DB/storage no persistida, repetir. |
| Releases A/B | PASS SIMULADO | Tests B 245/1,536; marcador B/health/login y A recuperado. No symlink/Apache real. |
| Scheduler | PARCIAL | Config America/Lima + lock y test; schedule:run sin tarea vencida, evaluador manual idempotente. No tarea permanente Windows. |
| Piloto seco extremo a extremo | NO | Solo smoke HTTP por ocho roles; no se ejecutaron mutaciones de toda la vida del caso. |
| Suite actual | PASS | 277/1,785; MySQL concurrente 6/58; audit Composer/NPM sin advisories. |
| Incidentes P0 observados | Ninguno | QA acotado. No equivale a evaluacion completa de seguridad. |
| P1 | ABIERTO | Fase 3 no versionada; RTO restauracion sin cronometro limpio; flujo seco pendiente. |

## Acciones para reabrir

1. Consolidar y commitear las dos migraciones/cambios de Fase 3 como un artefacto coherente; producir otra copia limpia y repetir instalacion/pruebas.
2. Repetir backup/restauracion desde cero solo en las dos DB autorizadas; guardar duracion de restauracion y RPO/RTO medibles.
3. Completar recorrido de caso de extremo a extremo con datos sinteticos y ocho roles, verificando stock, auditoria, documentos y facturacion.
4. Ejecutar scheduler durante ventana observada sin crear tarea permanente, revisar logs y lock; despues aprobar el mecanismo de ejecucion operativo.
5. Revisar las brechas de seguridad/privacidad/performance y volver a firmar esta acta.

Este NO-GO es solo para promover el piloto local a operacion. No se ha realizado despliegue remoto y no se concede ni se recomienda `GO PRODUCCION`.
