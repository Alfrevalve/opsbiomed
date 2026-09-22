# Piloto seco por roles

Fecha: 2026-09-20. Datos unicamente de `PilotDemoSeeder` en `ops_biomed_staging_local`; cambios de lectura y login, sin transacciones clinicas del caso.

## Accesos validados

| Cuenta piloto | Validacion HTTP |
|---|---|
| `admin@ops.test` | Dashboard, casos, inventario, forecast, facturacion y usuarios: 200. |
| `jefe.linea@ops.test` | Casos, inventario y reportes: 200; administracion de usuarios: 403. |
| `dt@ops.test` | Fallas, devoluciones e inventario: 200; billing: 403. |
| `almacen@ops.test` | Inventario, devoluciones y casos: 200; billing: 403. |
| `instrumentista@ops.test` | Casos y devoluciones: 200; billing: 403. |
| `comercial@ops.test` | Reporte comercial, estado de billing y documentos: 200; forecast: 403. Importes no visibles. |
| `cobranza@ops.test` | Billing y reporte de cobranza: 200; forecast: 403. |
| `gerencia@ops.test` | Billing, reportes y forecast: 200. |

Las comprobaciones de permisos no completan el piloto de extremo a extremo. No se creo solicitud nueva ni se ejecutaron reserva/preparacion, escaneo, consumo, retorno, cierre, conciliacion, valorizacion, factura y pago sobre el caso demo. No registrar esta fase como piloto funcional completado.

## Recorrido pendiente

1. Crear caso sintetico adicional en staging desde Administrador/Comercial autorizado.
2. Programar y revisar conflictos de agenda.
3. Reservar lote elegible; comprobar la misma disponibilidad y stock neto.
4. Preparar, asignar recurso y comprobar trazabilidad.
5. Registrar consumo y retorno; comprobar cuarentena/inspeccion o falla segun resultado.
6. Cerrar y conciliar; verificar auditoria e inventario.
7. Valorizar y completar el proceso financiero con Cobranza.
8. Consultar reportes y SLA, y verificar los estados finales por rol.

Hacer el recorrido en datos desechables y detenerse ante cualquier discrepancia de stock, evidencia o autorizacion.
