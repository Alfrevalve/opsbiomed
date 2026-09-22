# Plan de fortalecimiento OPS BIOMED MR8

## Fases 1 y 2

**Estado:** cambios locales implementados previamente. Incluyen baseline, rutas de acceso, idempotencia de reservas, bloqueos para stock/import, concurrencia de agenda, pruebas de replay y rollback, autorizacion documental y sanitizacion de CSV. Ver auditoria y matriz de riesgos.

## Fase 3: integridad, disponibilidad y continuidad

**Estado de implementacion local:** liberacion total/cancelacion atomica, auditoria, idempotencia, compensacion de evidencia, reintento de imports, concurrencia MySQL y scripts guarded de staging preparados. **Estado de salida del piloto: NO-GO** porque no existe staging cPanel ensayado.

### Hecho y probado localmente

1. `reservations.release` solo para Administrador, Jefe de Linea y Almacen.
2. No se libera directamente material despachado, internado, consumido, conciliado, cerrado, devuelto o con falla; debe seguir devolucion/inspeccion.
3. La cancelacion presenta vista previa, exige motivo/confirmacion y hace rollback completo si una reserva activa no es elegible.
4. El flujo conserva stock fisico, auditoria e idempotencia; 14 Feature tests cubren permisos, estados, replay, cancelacion y rollback de auditoria.
5. Archivo privado recien creado se elimina si falla su transaccion SQL; archivos anteriores no se tocan.
6. Seis pruebas con dos procesos independientes MySQL pasan: reserva de ultima unidad, imports sobre producto/lote/almacen, rollback por reserva activa, cruce de instrumentista, cruce de equipo reutilizable y ajustes concurrentes. MySQL local 8.4.3, aislamiento REPEATABLE-READ, `ops_biomed_test`.
7. `laravel/framework` limitado a `^12.0`; lock recalculado sin actualizacion general.
8. Workflow remoto queda bloqueado; `deploy-cpanel.sh` y `backup-cpanel.sh` dry-run por defecto y rechazan produccion.

## Bloqueadores para el piloto

1. Proveer staging cPanel y confirmar PHP 8.3, Composer, cron, symlinks/LiteSpeed, flock, GPG, mysqldump, espacio y rutas privadas.
2. Ejecutar backup cifrado de DB/configuracion/evidencias con checksum y copia secundaria si es posible.
3. Restaurar DB y storage en instancia aislada; medir RPO <= 1 hora y RTO <= 2 horas.
4. Ensayar release nuevo, health check fallido, rollback de codigo y compatibilidad de esquema con datos ficticios.
5. Configurar cron cada minuto y demostrar alertas horarias, ausencia de solapamiento, zona America/Lima y contenido sin datos sensibles.
6. Aceptacion del propietario para cada P1 residual y checklist de roles en staging.

## Siguientes fases

- Privacidad: retencion, minimizacion, control de exportaciones y permisos por registro.
- Rendimiento: medir consultas y volumen representativo de catalogo/casos antes de optimizar.
- QA piloto: pruebas de navegador responsive, accesibilidad, alertas y entrenamiento por rol.
- Automatizacion externa/IA: mantener como apoyo revisado por humanos; toda integracion requiere minimizacion, permiso, auditoria y aprobacion explicita.

## Puertas de aprobacion

No habilitar el job remoto ni realizar migraciones/restauraciones en produccion hasta cerrar los bloqueadores y recibir aprobacion explicita del propietario. RPO/RTO son objetivos aprobados, no resultados demostrados.
