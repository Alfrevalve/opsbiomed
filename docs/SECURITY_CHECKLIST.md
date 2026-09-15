# Checklist de seguridad web

## Antes de piloto

- APP_KEY generada.
- APP_DEBUG=false fuera de local.
- Usuarios nominales, sin cuentas compartidas.
- Roles y permisos probados.
- Middleware de usuario activo habilitado.
- Policies aplicadas a casos, reservas, inventario, fallas, aprobaciones y facturacion.
- CSRF activo en formularios web.
- Rate limit activo en login, API e importador.
- Storage privado para evidencias.
- Logs sin datos sensibles del paciente.
- Backups configurados y restore probado.

## Eventos que deben auditarse

- Creacion y cambio de estado del caso.
- Reserva y liberacion de stock.
- Salida de material.
- Consumo y devolucion.
- Diferencia de inventario.
- Reporte y liberacion de falla.
- Costo cero, canje, cesion y excepcion.
- Registro de factura, OC, abono y deuda.
