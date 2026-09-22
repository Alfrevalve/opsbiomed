# Pruebas de seguridad local

Fecha: 2026-09-20. Esta es evidencia de QA acotada, no una auditoria de penetracion ni certificacion. No se enviaron ataques a servicios externos.

## Ejecutado

- Suite actual: 277 tests / 1,785 assertions; suite MySQL separada: 6 / 58.
- Prueba documental enfocada: 12 / 58; Comercial recibe 403 al consultar/descargar documentos financieros por ID y no puede cargarlos como evidencia de caso.
- Matriz de ocho cuentas piloto por HTTP: accesos autorizados 200, no autorizados 403, sin respuestas 500; Comercial sin importes, Forecast denegado.
- Uploads: pruebas existentes de MIME/extensiones/tamano, CSRF en formularios Laravel, trazas privadas y soft delete; CSV/formulas y rutas controladas cubiertos por Feature tests.
- `APP_DEBUG=false` en staging; inspeccion de logs locales no encontro patrones de clave, bearer o password.
- Cache/sesion persistentes en staging local; tarea SLA con `withoutOverlapping`; 4 alertas en primera evaluacion, 0 duplicados en segunda; cola 0 y jobs fallidos 0.
- `composer audit --locked`: 0 advisories. `npm audit`: 0 vulnerabilidades.

## Correccion durante QA

Las evidencias de factura/OC ligadas al caso podian aparecer en consultas documentales generales; las policies permitian intentar operaciones por ID. Se agrego visibilidad filtrada por permiso financiero/de aprobacion a las consultas, carga, detalle, descarga, validacion, borrado y colecciones embebidas. Pruebas de regresion pasan.

## Pendiente

- Repetir verificacion con datos anonimizados y seguridad revisada por responsable designado.
- Revisar rate-limit de login, expiracion/revocacion de sesiones, CSRF y casos IDOR adicionales con una matriz independiente.
- Confirmar headers HTTP/TLS, permisos de filesystem, limites PHP/MySQL y aislamiento de backups en el hosting objetivo.
- Revalidar permisos y descargas en el servidor staging real antes de cargar evidencia real.
