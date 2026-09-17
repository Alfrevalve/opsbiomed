# Plan de Automatizacion OPS BIOMED MR8

Estado: Propuesto. Requiere aprobacion funcional y tecnica por fase.

## Objetivo

Reducir tareas manuales y la dependencia de WhatsApp, correos y hojas de calculo, manteniendo:

- Autorizacion humana.
- Trazabilidad y auditoria.
- Proteccion de datos personales y de salud.
- Ninguna decision clinica, tecnica, financiera o administrativa autonoma.

## Estado actual

La plataforma ya cuenta con una base para automatizar de forma controlada:

- Evaluacion horaria de SLA y alertas operativas.
- Registro de notificaciones internas.
- Reservas transaccionales y validacion de elegibilidad.
- Cobertura, forecast y semaforo de inventario.
- Deteccion de conflictos de agenda y recursos.
- Transiciones de estado controladas.
- Auditoria, documentos y trazabilidad por codigo.
- Configuracion inicial para integracion con Slack.

## Fase 0: Gobierno y base tecnica

Implementar antes de conectar servicios externos:

- Permisos `automation.view`, `automation.manage` y `automation.approve`.
- Catalogo de automatizaciones activas.
- Activacion independiente por modulo.
- Modo simulacion.
- Idempotencia y control de duplicados.
- Cola de trabajos, reintentos y registro de fallos.
- Auditoria para `suggested`, `approved`, `rejected`, `executed` y `failed`.

Autorizan: Administrador y Gerencia.

## Fase 1: Centro de alertas y tareas SLA

Prioridad maxima. Extender el modulo SLA existente con:

- Bandeja de tareas pendientes.
- Alertas internas por usuario y rol.
- Escalamiento a responsable, Jefe de Linea, Direccion Tecnica y Gerencia.
- Notificaciones por correo.
- Preparacion para Slack.
- Reintentos, confirmacion de lectura y resultado de entrega.

Casos iniciales:

- Solicitud sin reserva.
- Cirugia proxima sin cobertura.
- Falla critica sin revision.
- Devolucion pendiente de inspeccion.
- Documento pendiente de validacion.
- Caso cerrado sin facturacion.
- Deuda vencida.
- Costo cero pendiente de aprobacion.

Estas alertas informan y escalan. No cambian estados automaticamente.

## Fase 2: Automatizacion preoperatoria

Crear checklist por cirugia y recordatorios a 48 horas, 24 horas y 4 horas para:

- Institucion, medico y fecha confirmados.
- Instrumentista asignado.
- Material reservado.
- Cobertura validada.
- Conflictos revisados.
- Documentos minimos cargados.
- Deuda y alertas relevantes.

El sistema puede sugerir "caso listo" o "caso incompleto", pero el responsable confirma.

## Fase 3: Agenda y recursos

Integrar calendario operativo, inicialmente en modo lectura:

- Exportacion `.ics`.
- Agenda diaria, semanal y proximas 48 horas.
- Instrumentistas y recursos reutilizables.
- Conflictos por horario, motor, consola, pedal, acople o set.

Se pueden detectar conflictos y proponer reasignaciones. No se reasignan recursos ni se confirma una cirugia sin autorizacion.

## Fase 4: Entrada automatica de solicitudes

Iniciar con correo y evaluar despues WhatsApp empresarial:

1. Recibir el mensaje.
2. Extraer datos preliminares.
3. Crear borrador.
4. Mostrar campos faltantes.
5. Revisar con usuario autorizado.
6. Confirmar la solicitud.

Nunca crear casos confirmados automaticamente.

## Fase 5: Inventario y ERP

Conectar API, SFTP, CSV o Excel programado solamente mediante staging:

1. Importar a staging.
2. Validar columnas, lotes, fechas, almacenes y cantidades.
3. Generar diferencias y errores.
4. Revisar cambios contra el catalogo actual.
5. Confirmar manualmente.
6. Ejecutar commit transaccional.
7. Auditar el resultado.

No actualizar inventario final sin confirmacion.

## Fase 6: Documentos y cierre

Automatizar checklist y recordatorios para solicitud, consumo, devolucion, inspeccion, falla, OC, factura y costo cero.

El sistema puede detectar documentos faltantes y diferencias de consumo. El cierre quirurgico continua requiriendo confirmacion humana.

## Fase 7: Facturacion y cobranza

Automatizar tareas administrativas:

- Borrador de valorizacion.
- Sugerencia de precio vigente.
- Deteccion de casos sin OC o factura.
- Alertas de vencimiento.
- Resumen de deuda por institucion.
- Reportes periodicos de cobranza.

Requieren autorizacion explicita: aprobar costo cero, emitir factura, registrar pago y enviar comunicaciones externas.

## Fase 8: IA asistida

Usos permitidos con revision humana:

- Clasificar solicitudes.
- Resumir incidencias.
- Detectar campos faltantes.
- Redactar comunicaciones operativas.
- Resumir reportes.
- Sugerir equivalencias de productos.

Toda salida debe identificarse como: "Sugerencia asistida. Requiere validacion humana."

Usos prohibidos: diagnostico, decision clinica, liberacion de fallas, liberacion de inventario, aprobacion de costos, cierre de cirugias y facturacion autonoma.

## Integraciones candidatas

- SMTP, Resend o Postmark para correo.
- Slack para alertas internas.
- Google Calendar o Microsoft 365 en lectura inicial.
- WhatsApp empresarial mediante proveedor autorizado.
- ERP por API, SFTP o archivos controlados.
- Sistema contable para consulta y conciliacion posterior.

Cada integracion requiere credenciales separadas, permisos minimos, trazabilidad, politica de datos y modo de prueba.

## Criterios de aprobacion por fase

Antes de activar una automatizacion se debe verificar:

- Permiso y rol autorizado.
- Confirmacion humana para cambios de datos o estados.
- Auditoria completa.
- Proteccion de datos sensibles.
- Reintentos y manejo de errores.
- Ausencia de duplicados.
- Pruebas funcionales y de autorizacion.
- Modo simulacion o vista previa.
- Mecanismo para detener la automatizacion.

## Orden recomendado

1. Gobierno de automatizaciones.
2. Centro de tareas y escalamiento SLA.
3. Recordatorios preoperatorios.
4. Agenda y conflictos.
5. Solicitudes preliminares por correo.
6. Integracion ERP e inventario.
7. Automatizacion documental.
8. Facturacion y cobranza.
9. IA asistida.

## Primer entregable recomendado

Centro de tareas, notificaciones y escalamiento SLA. Aprovecha el modulo existente, reduce seguimiento manual y no modifica automaticamente inventario, reservas, cierres ni facturacion.
