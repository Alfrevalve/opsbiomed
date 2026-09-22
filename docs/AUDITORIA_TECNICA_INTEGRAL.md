# Auditoria tecnica OPS BIOMED MR8

**Actualizada:** 2026-09-20. Revision local de codigo, configuracion no secreta y pruebas. No se accedio ni modifico produccion; cPanel no estuvo disponible. No es certificacion de seguridad, auditoria legal ni validacion clinica.

## Resumen de Fase 3

- Laravel se mantiene en major 12; el runtime local verificado anteriormente es Laravel 12.69.2 / PHP 8.3.33. `composer.json` ahora restringe a `^12.0`; composer.lock se actualizo solo en content hash, sin actualizar paquetes.
- Se implementaron liberacion total de reservas y cancelacion atomica bajo `reservations.release`, con motivo, confirmacion, vista previa, trazabilidad e idempotencia.
- No se libera material que salio del control de Almacen, se interno, consumio, concilio, devolvio, tuvo falla o pertenece a caso cerrado. Debe continuar por devolucion e inspeccion.
- Se evita stock fisico negativo, no se suma stock al liberar y el archivo privado recien subido se compensa si la transaccion falla.
- Prueba real de concurrencia con procesos MySQL independientes: 6 tests / 58 assertions; base exclusivamente `ops_biomed_test`, MySQL 8.4.3, aislamiento `REPEATABLE-READ`.
- Un deadlock transitorio entre imports se resuelve con reintentos limitados a tres transacciones; los imports concurrentes no duplican producto/lote y no reemplazan un lote reservado.
- Workflow de produccion desactivado. Scripts de backup/deploy exigen staging y hacen dry-run por defecto.
- Scheduler configura America/Lima y `withoutOverlapping`; cron real del hosting aun no validado.

## Pruebas de concurrencia MySQL

| Escenario | Resultado |
|---|---|
| Dos reservas por la ultima unidad | Solo una reserva confirmada; no sobre-reserva |
| Dos imports mismos producto/lote/almacen | Una identidad de lote; el import mas reciente es el vigente |
| Imports concurrentes ante reserva activa | Ambos se rechazan y rollback conserva cantidad/metadata/reserva |
| Mismo instrumentista en horario coincidente | Solo una asignacion confirmada |
| Mismo recurso reutilizable en horario coincidente | Solo una asignacion confirmada |
| Ajustes concurrentes al mismo lote | Resultado serializado; ambos movimientos auditados |

Limites: es una prueba local del codigo y de MySQL 8.4.3; no mide hosting ni replica latencia, configuracion o topologia cPanel. La tabla `ops_biomed_test` se usa solo por el harness PHPunit de integracion y se trunca al iniciar la suite; su configuracion exacta esta ignorada por Git. `migrate:status` confirma 38 migraciones y 52 tablas en esa base al cierre de QA.

Estado final del fixture MySQL: 1 producto ficticio y 1 lote con cantidad fisica 11 (inicio 10, ajustes +2 y -1), 2 ajustes/auditorias y 0 reservas activas. El seeder registra `reservations.release` para Administrador, Almacen y Jefe de Linea. Son datos descartables de `ops_biomed_test`, no el catalogo piloto.

## Continuidad y despliegue

Objetivos aprobados, no medidos: RPO <= 1 hora y RTO <= 2 horas. No existe evidencia de backup/restore, cron, despliegue o rollback en cPanel. No se conoce si el plan contratado soporta GPG, `mysqldump`, cron cada minuto, SSH, symlinks/LiteSpeed, `flock` o almacenamiento offsite.

`deploy-production.yml` no tiene acceso remoto y su job de despliegue tiene `if: false`. Los scripts locales usan igualdad exacta de rutas y modo ensayo por defecto; cualquier ejecucion requiere staging, backup cifrado, checksum, health check y release anterior. Ver `DESPLIEGUE_PRODUCCION.md` y `BACKUP_Y_RESTAURACION.md`.

## Decision

**Piloto: NO-GO.** La liberacion y la concurrencia local pasan. Bloquean la recomendacion: staging cPanel no ensayado, backup cifrado no restaurado, rollback no ejecutado y cron/alertas no validados en entorno equivalente. Ningun cambio se aplico a produccion.
