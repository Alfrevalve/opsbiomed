# OPS BIOMED MR8 - Contexto para IA

Este archivo resume las reglas que una IA debe respetar al modificar el sistema.

## Proposito

OPS BIOMED MR8 es una torre de control quirurgica para controlar solicitudes, reservas, internamiento, preoperatorio, consumo, retorno, conciliacion, facturacion, cobranza, fallas, forecast e inteligencia comercial de la linea Midas Rex MR8.

## Entidad central

La entidad central es SurgeryCase. Todo consumo, reserva, evidencia, falla, aprobacion, factura y seguimiento debe vincularse a un caso cuando exista.

## Reglas duras

- Ninguna cirugia se atiende sin registro minimo.
- Ningun material sale sin disponibilidad y autorizacion.
- YSAN no cuenta como stock inmediato; disponibilidad minima 48 horas.
- Almacen desvalorizado nunca cuenta como stock elegible.
- Stock minimo por combinacion exacta: 3 cirugias.
- Stock objetivo por combinacion exacta: 5 cirugias.
- Toda falla que compromete uso seguro genera bloqueo preventivo.
- Toda diferencia entre enviado, usado y devuelto se investiga.
- Todo costo cero, canje o excepcion requiere aprobacion y evidencia.
- Todo caso debe alimentar inventario, forecast, cobranza y analisis comercial.

## Seguridad

- No exponer datos sensibles del paciente en logs, URLs, dashboards publicos o errores.
- Usar policies y permisos para cada accion critica.
- Usar storage privado para evidencias.
- Auditar cambios de reserva, consumo, falla, aprobacion, inventario y facturacion.
- No usar SQL concatenado.
- No usar HTML sin escapar con datos de usuario.
