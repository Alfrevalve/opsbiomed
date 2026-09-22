# Modelo de datos criticos

Fuente: migraciones de `database/migrations`. En la base desechable de QA `ops_biomed_test` se verificaron 38 migraciones aplicadas y 52 tablas el 2026-09-20. No extrapolar ese estado a produccion.

| Dominio | Tablas principales | Controles relevantes |
|---|---|---|
| Identidad | `users`, `roles`, `permissions`, pivotes Spatie | Email unico; usuario activo verificado por middleware; autorizacion server-side |
| Maestros | `institutions`, `doctors`, `patients`, `surgery_types`, `kit_rules` | Casos conservan referencias; no borrar maestros en uso |
| Catalogo/stock | `products`, `warehouses`, `inventory_lots`, `inventory_adjustments`, `inventory_import_issues` | Codigo de producto unico; elegibilidad excluye estados no utilizables y resta reservas activas |
| Importacion | `catalog_imports`, `catalog_import_rows`, `inventory_import_issues` | Staging y commit transaccional; historico preservado; import concurrente no duplica producto/lote |
| Cirugia/reserva | `surgery_cases`, `reservations`, `case_preparations`, `case_resource_assignments`, `reservation_release_operations` | Codigo de caso e idempotency UUID unicos; reservas enlazan caso/lote; liberacion conserva historial |
| Consumo/retorno | `case_materials_sent`, `case_materials_used`, `case_returns`, `failures` | Conciliacion; retorno exige inspeccion; falla puede bloquear lote |
| Valorizacion/cobro | `case_valuations`, `case_valuation_lines`, `approvals`, `billing_records`, `case_reconciliations`, `product_prices` | Caso valorizado y facturacion relacionados; costo cero aprobado no suma deuda |
| Evidencia/auditoria | `document_evidences`, `audit_logs`, `trace_events`, `ai_usage_logs` | Storage privado; trazabilidad/auditoria; no guardar prompts sensibles |
| Alertas/manual | `operational_slas`, `operational_alerts`, `notification_logs`, `manual_categories`, `manual_articles`, `manual_progress` | Estados controlados y claves para evitar duplicados |

## Relaciones operativas y locks

- Caso enlaza institucion, medico, paciente y tipo de cirugia. Reserva enlaza caso y lote; activa descuenta disponibilidad neta.
- Liberar cambia solo estado de reserva, no cantidad fisica. Las operaciones llevan clave idempotente, actor, motivo, timestamp y auditoria.
- Cancelacion bloquea caso, reservas y lotes; solo cancela si todas las reservas activas son elegibles. Material entregado, consumido, conciliado, devuelto o fallado requiere su flujo de devolucion/inspeccion.
- `case_materials_used` guarda usado, abierto, devuelto, fallado y diferencia. La conciliacion mantiene el historial de consumo y retorno.
- `case_returns` inicia pendiente de inspeccion; liberar, cuarentena o bloqueo ocurre al inspeccionar.
- `document_evidences` es polimorfico y valida autorizacion tanto del documento como de la entidad destino.

## Cambios de idempotencia

- `2026_09_20_141845_add_idempotency_key_to_reservations_table` agrega UUID nullable unique sin modificar filas/stock existentes.
- `2026_09_20_151056_create_reservation_release_operations_table` registra tipo de operacion, clave idempotente, caso, reserva, actor, motivo y resultado. No eliminar registros de auditoria/operacion.

## Antes de produccion

Verificar `migrate:status`, foreign keys e indices en staging MySQL con backup restaurable. No ejecutar migraciones/limpieza/restauracion en bases que no sean el entorno explicitamente aprobado. InnoDB y su concurrencia no quedan demostrados por SQLite.
