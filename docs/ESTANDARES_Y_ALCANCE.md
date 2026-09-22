# Estandares, alcance y limites de revision

## Controles tecnicos

- Stack de esta fase: Laravel 12, PHP 8.3, Blade/Breeze, Vite y Tailwind 4. No actualizar versiones mayores como parte de un deploy rutinario.
- Cambios de stock, reserva, cancelacion, importacion o auditoria deben autorizar, validar en servidor y usar transacciones/bloqueos sobre el recurso compartido.
- Ninguna liberacion/cancelacion cambia stock fisico; el flujo conserva historial y requiere motivo.
- Pruebas de integracion concurrente deben usar sesiones/procesos MySQL independientes y una base desechable dedicada. SQLite secuencial no prueba locks InnoDB.
- Datos personales/de salud y secretos no deben aparecer en logs, manifiestos, CSV sin autorizacion o documentacion. Evidencias quedan en storage privado.
- Funciones de alerta y sugerencia son apoyo operativo; una persona autorizada valida la accion.

## Fase 3 ejecutada localmente

- Permiso `reservations.release` solo para Administrador, Jefe de Linea y Almacen.
- Liberacion total, cancelacion atomica y auditada, idempotencia, vista previa de elegibilidad y bloqueo de material entregado/consumido/conciliado/devuelto/fallado.
- Recuperacion compensatoria de archivo privado ante rollback de ajuste.
- MySQL independiente: 6 pruebas/58 aserciones sobre `ops_biomed_test`, motor MySQL 8.4.3, aislamiento `REPEATABLE-READ`.
- Reintento acotado del commit de catalogo para deadlocks transitorios.
- Restriccion `laravel/framework` a `^12.0` y lock recalculado sin actualizar paquetes.
- Workflow remoto de produccion desactivado; scripts de backup/release solo aceptan staging y modo de ensayo por defecto.

## Limitaciones

- No se accedio ni desplego a produccion. No se accedio a cPanel/staging ni se probaron sus capacidades.
- No se probo un backup/restore real, un rollback de release, cron cPanel, TLS/headers servidos por LiteSpeed ni permisos del usuario web.
- Las pruebas MySQL locales no equivalen a staging. No se hizo pentest externo, auditoria legal, certificacion ni validacion clinica/regulatoria.
- RPO 1 hora y RTO 2 horas son objetivos aprobados, aun no medidos.

## Cambios que requieren aprobacion explicita

Cambios de reglas clinicas/comerciales/contables, retencion, permisos globales, `.env`, produccion, migraciones destructivas, restauraciones o envio de informacion a terceros. El workflow de despliegue remoto permanece bloqueado hasta cerrar estas brechas y aprobar el piloto.
