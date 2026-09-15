# Especificación técnica para OPS BIOMED MR8

**Torre de Control Quirúrgica para la línea de craneotomía Midas Rex MR8**

Representaciones Médicas Biomed S.A.C.

*Versión 1 para diseño funcional técnico y construcción en Laravel*

Este documento define cómo llevar la operación MR8 a una aplicación web Laravel con control operativo, trazabilidad, seguridad de datos, auditoría y reglas de negocio. La prioridad inicial es construir un MVP útil para cirugías reales: catálogo, reglas de kit, casos quirúrgicos, reservas, consumo, retorno, conciliación y dashboard de ruptura.

La aplicación no reemplaza el criterio clínico ni emite indicaciones médicas. Su función es controlar operaciones, inventario, evidencias, aprobaciones, facturación y seguimiento comercial.

## Decisión técnica principal

| **Elemento**  | **Decisión recomendada**                  | **Motivo**                                                                                       |
|---------------|-------------------------------------------|--------------------------------------------------------------------------------------------------|
| Framework     | Laravel 13.x con PHP 8.3 o superior       | Última línea documentada; usar Laravel 12.x solo si el servidor local lo exige                   |
| Frontend MVP  | Blade Livewire Tailwind                   | Menor complejidad y avance rápido para formularios y tableros internos                           |
| Base de datos | PostgreSQL preferente o MySQL compatible  | PostgreSQL facilita JSONB auditoría y consultas analíticas; MySQL es válido si ya está instalado |
| Autenticación | Laravel starter kit más Spatie Permission | Login sólido más roles y permisos granulares                                                     |
| API           | Sanctum para endpoints internos y móviles | Sesiones seguras para web y tokens para servicios controlados                                    |
| Colas         | Laravel Queue y Scheduler                 | Alertas, recordatorios, importaciones y procesos diferidos                                       |
| Archivos      | Storage privado                           | Evidencias quirúrgicas, hojas de consumo y documentos no deben quedar públicos                   |
| Auditoría     | Tabla audit_logs obligatoria              | Toda decisión crítica debe quedar trazada                                                        |

## Objetivos del sistema

- Registrar toda solicitud de cirugía antes de comprometer equipo o material.

- Validar disponibilidad real por combinación exacta de producto código lote serie almacén estado y fecha.

- Garantizar como mínimo 3 cirugías completas y objetivo 5 cirugías completas por combinación crítica.

- Evitar cruces de equipo aditamento consumible e instrumentista.

- Bloquear automáticamente material vencido desvalorizado observado reservado con falla o en cuarentena.

- Cerrar cada caso con consumo evidencia retorno conciliación valorizado o motivo formal de no facturación.

- Alimentar inventario forecast cobranza y análisis comercial desde cada caso atendido.

- Construir una base segura para crecer hacia ERP quirúrgico.

## Alcance del MVP

| **Incluido**                       | **No incluido inicialmente**           | **Criterio de cierre**                                 |
|------------------------------------|----------------------------------------|--------------------------------------------------------|
| Catálogo MR8 con importación Excel | Integración automática con ERP externo | El usuario puede importar y validar stock por lote     |
| Reglas de kit por tipo de cirugía  | Motor clínico de recomendación médica  | El sistema calcula requerimiento operativo por cirugía |
| Casos quirúrgicos y reservas       | Aplicación móvil nativa                | Cada caso reserva stock y equipo sin cruces            |
| Checklists preoperatorios          | Firma digital avanzada                 | Se registra preparación confirmación y responsable     |
| Consumo y retorno                  | OCR automático de hojas firmadas       | El caso cierra con consumo evidencia y retorno         |
| Conciliación y dashboard           | Business intelligence completo externo | Se visualiza ruptura deuda fallas y casos abiertos     |

## Arquitectura general

OPS BIOMED debe separarse en capas simples. La capa web captura datos y muestra tableros. La capa de dominio aplica reglas de cirugía, stock, reservas y cierre. La capa de datos conserva catálogo, inventario, casos, consumos, documentos, auditoría y configuración.

| **Capa**    | **Responsabilidad**                                             | **Componentes Laravel**                                |
|-------------|-----------------------------------------------------------------|--------------------------------------------------------|
| Interfaz    | Formularios tableros filtros y vistas por rol                   | Blade Livewire Tailwind components                     |
| Aplicación  | Casos reservas checklists consumo retornos y facturación        | Controllers Form Requests Jobs Notifications Policies  |
| Dominio     | Reglas de kit semáforo stock elegible vencimiento y excepciones | Services Actions Enums Value Objects                   |
| Datos       | Persistencia transaccional y trazabilidad                       | Eloquent Migrations Seeders Observers                  |
| Integración | Importación Excel exportes reportes y futuras conexiones        | Queues Laravel Excel Storage Scheduler                 |
| Seguridad   | Autenticación autorización auditoría y protección de datos      | Middleware Policies Gates Sanctum RateLimiter AuditLog |

## Módulos funcionales

| **Módulo**             | **Función**                                                                 | **Primera versión**                                         |
|------------------------|-----------------------------------------------------------------------------|-------------------------------------------------------------|
| Catálogo e inventario  | Importar productos códigos lotes series vencimientos almacenes y cantidades | Subida Excel vista de validación y stock elegible           |
| Reglas de kit          | Mantener combinaciones críticas por tipo de cirugía                         | Cervical cráneo endoscópica tubular nasal y torácica lumbar |
| Casos quirúrgicos      | Crear y seguir la cirugía desde solicitud hasta cierre                      | Formulario único con estados controlados                    |
| Reservas               | Bloquear equipos aditamentos consumibles e instrumentista                   | Validación automática contra cruces y stock                 |
| Preoperatorio          | Confirmar SOP llegada material equipo funcional y personal                  | Checklist digital con responsable y hora                    |
| Consumo                | Registrar abierto usado devuelto adicional y evidencia                      | Cierre con hoja de consumo link o archivo privado           |
| Conciliación           | Comparar enviado usado devuelto y bloquear diferencias                      | Alertas por faltante falla pérdida o negativo               |
| Facturación y cobranza | Valorizar consumo y controlar OC factura abono deuda                        | Registro manual con estados y alertas                       |
| Comercial y cesiones   | Medir uso oportunidades frecuencia acuerdos y expansión                     | Ficha por institución médico y equipo cedido                |
| Dashboard              | Mostrar operación del día riesgo de ruptura fallas deuda y forecast         | Vista ejecutiva y operativa                                 |

## Entidad central del sistema

La entidad central es el caso quirúrgico. Todo registro relevante debe quedar vinculado a un caso o a un evento maestro cuando todavía no exista cirugía confirmada. Esto evita información suelta en correos hojas y mensajes.

CasoQuirurgico -> Solicitud -> ValidacionComercial -> Reserva -> Preparacion -> Atencion -> Cierre -> Conciliacion -> Facturacion -> Analisis

| **Dato mínimo**     | **Uso operativo**                                  | **Regla**                                       |
|---------------------|----------------------------------------------------|-------------------------------------------------|
| Institución         | Agenda logística cobranza y análisis por cuenta    | Obligatorio para crear caso                     |
| Médico              | Consumo oportunidad y seguimiento comercial        | Obligatorio salvo emergencia con regularización |
| Paciente            | Trazabilidad documental y consumo                  | Guardar solo datos necesarios                   |
| Fecha y hora        | Reserva y prevención de cruces                     | Obligatorio antes de reservar                   |
| Tipo de cirugía     | Cálculo de kit requerido                           | Debe venir de lista controlada                  |
| Material solicitado | Comparación contra reglas de kit                   | Puede iniciar por texto pero debe normalizarse  |
| Origen solicitud    | Trazabilidad de correo WhatsApp llamada formulario | Siempre obligatorio                             |

## Modelo de datos sugerido

| **Tabla**            | **Propósito**                   | **Campos críticos**                                                                        |
|----------------------|---------------------------------|--------------------------------------------------------------------------------------------|
| users                | Usuarios del sistema            | name email password active last_login_at                                                   |
| roles permissions    | Control de acceso               | role permission guard_name                                                                 |
| institutions         | Cuentas hospitales y clínicas   | name ruc billing_policy debt_status agreement_type                                         |
| doctors              | Médicos tratantes y contactos   | name specialty institution_id commercial_owner_id                                          |
| patients             | Datos mínimos de paciente       | code full_name document encrypted_fields                                                   |
| surgery_cases        | Caso central                    | case_code status institution_id doctor_id patient_id surgery_type_id scheduled_at priority |
| surgery_types        | Tipos de cirugía                | name active default_rule_set_id                                                            |
| kit_rules            | Reglas de combinación requerida | surgery_type length_cm diameter_mm cut_type component_type min_qty target_qty criticality  |
| products             | Catálogo maestro                | product_code normalized_code name family subfamily regulatory_record tracking_type         |
| inventory_lots       | Existencia trazable             | product_id lot serial expiry warehouse_id quantity status eligible_flag                    |
| warehouses           | Almacenes                       | name type lead_time_hours counts_as_immediate                                              |
| reservations         | Bloqueo de stock                | case_id inventory_lot_id quantity status reserved_by expires_at                            |
| case_materials_sent  | Material enviado                | case_id inventory_lot_id quantity guide_number sent_at                                     |
| case_materials_used  | Consumo real                    | case_id inventory_lot_id opened_qty used_qty unused_opened_qty evidence_id                 |
| case_returns         | Retorno e inspección            | case_id inventory_lot_id returned_qty condition inspected_by                               |
| failures             | Fallas e incidencias            | case_id inventory_lot_id severity status preventive_block                                  |
| approvals            | Aprobaciones                    | case_id type requested_by approved_by status evidence                                      |
| billing_records      | Facturación y cobranza          | case_id amount invoice_status purchase_order payment_status debt_days                      |
| commercial_followups | Seguimiento comercial           | case_id institution_id doctor_id opportunity_stage next_action_at                          |
| audit_logs           | Trazabilidad de cambios         | user_id auditable_type auditable_id action before after ip user_agent                      |

## Estados operativos del caso

| **Estado**               | **Significado**                                            | **Entrada permitida**                                            | **Salida permitida**                 |
|--------------------------|------------------------------------------------------------|------------------------------------------------------------------|--------------------------------------|
| solicitado               | Caso registrado con datos mínimos                          | Creación manual formulario importación o emergencia regularizada | validacion_comercial cancelado       |
| validacion_comercial     | Se revisa precio entidad facturación OC excepción o deuda  | Solicitud completa                                               | reservado observado cancelado        |
| reservado                | Equipo material e instrumentista bloqueados                | Stock y autorizaciones válidas                                   | preparacion reprogramado cancelado   |
| preparacion              | Almacén arma material y guías                              | Reserva activa                                                   | internado observado                  |
| internado                | Material trasladado y recibido                             | Guía y recepción conforme                                        | preoperatorio_confirmado observado   |
| preoperatorio_confirmado | SOP material equipo e instrumentista confirmados           | Checklist conforme                                               | en_cirugia cancelado                 |
| en_cirugia               | Atención activa                                            | Inicio real registrado                                           | pendiente_cierre observado           |
| pendiente_cierre         | Falta consumo retorno o evidencia                          | Fin de cirugía                                                   | conciliacion observado               |
| conciliacion             | Comparación enviado usado devuelto                         | Consumo y retorno registrados                                    | facturacion observado                |
| facturacion              | Valorización OC factura cobranza o sustento costo cero     | Conciliación sin bloqueo crítico                                 | cerrado observado                    |
| cerrado                  | Caso completo                                              | Todas las reglas de cierre cumplidas                             | Reapertura solo por rol autorizado   |
| observado                | Tiene faltante falla deuda excepción o evidencia pendiente | Cualquier etapa con alerta crítica                               | Estado anterior corregido o escalado |

## Reglas de negocio obligatorias

- Ninguna cirugía se atiende sin registro mínimo de solicitud.

- Ningún material sale sin validación de disponibilidad y autorización aplicable.

- YSAN cuenta como apoyo externo con disponibilidad mínima de 48 horas y no como stock inmediato.

- El almacén desvalorizado nunca cuenta como stock elegible.

- Toda emergencia se regulariza en el sistema aun si inició por llamada o WhatsApp.

- Toda falla técnica crea reporte y bloqueo preventivo cuando compromete uso seguro.

- Todo consumo debe tener evidencia o sustento documentado.

- Todo costo cero canje descuento o excepción requiere aprobación y evidencia.

- Toda devolución se inspecciona antes de volver a estar disponible.

- Toda diferencia entre enviado usado y devuelto se investiga antes de cierre.

- Toda deuda vencida escala a Administración Comercial Jefe de Línea y Gerencia.

- Todo caso alimenta análisis comercial inventario forecast y productividad.

## Reglas de kits MR8

| **Tipo de cirugía**     | **Requerimiento base**                                                                | **Crítico**                               | **Regla de control**                                              |
|-------------------------|---------------------------------------------------------------------------------------|-------------------------------------------|-------------------------------------------------------------------|
| Cervical                | Fresas 9 y 10 cm cortantes y diamantadas                                              | 1 2 y 3 mm con más prioridad a 2 mm       | No preparar si falta crítica salvo aprobación de emergencia       |
| Cráneo                  | Fresas 7 9 y 10 cm todas las medidas más iniciadora y cuchilla F2 o F3 según caso     | 3 4 y 5 mm                                | Cierre de preparación exige críticas completas                    |
| Endoscópica no nasal    | 14 cm convencional cortantes y diamantadas                                            | 2.2 y 3 mm                                | No usar 4 ni 5 mm como base                                       |
| Tubular                 | Telescópicas 12 y 14 cm todas las medidas más 14 cm convencional 3 4 y 5 mm           | Medidas disponibles por técnica           | Control por telescópica y backup convencional                     |
| Endoscópica nasal       | Telescópicas 12 y 14 cm todas las medidas más iniciadoras cuchilla y backup 7 9 14 cm | Todas las medidas definidas por kit nasal | Validar backup y accesorios antes de salida                       |
| Columna torácica lumbar | Fresas 9 10 y 14 cm 3 4 y 5 mm cortantes y diamantadas                                | 3 4 y 5 mm                                | Tipo preestablecido con iniciadora y cuchilla F1 F2 F3 según caso |

## Seguridad de aplicación web

La seguridad debe tratarse como requisito funcional. Cada historia de usuario debe tener validación, autorización, auditoría, pruebas y manejo de errores antes de darse por terminada.

| **Área**      | **Regla**                                                           | **Implementación Laravel**                                           | **Criterio de aceptación**                                  |
|---------------|---------------------------------------------------------------------|----------------------------------------------------------------------|-------------------------------------------------------------|
| Autenticación | Usuarios activos con contraseña fuerte y MFA para perfiles críticos | Starter kit Fortify o Breeze más 2FA para Admin Gerencia DT Cobranza | Login bloquea usuarios inactivos y fuerza 2FA por rol       |
| Autorización  | Deny by default y mínimo privilegio                                 | Policies Gates y Spatie Permission                                   | Un rol sin permiso no ve ni ejecuta acciones restringidas   |
| Sesiones      | Sesión segura con expiración e invalidación                         | SESSION_SECURE_COOKIE true SameSite strict timeout idle              | Cierre automático tras inactividad y logout invalida sesión |
| CSRF          | Todo formulario web mutante exige token                             | Middleware CSRF y directiva @csrf                                    | POST PUT PATCH DELETE fallan sin token válido               |
| Validación    | Entrada validada antes de tocar dominio                             | Form Requests reglas enums y listas blancas                          | Datos fuera de catálogo no pasan a reserva                  |
| Inyección SQL | No concatenar entrada del usuario                                   | Eloquent Query Builder bindings y whitelist para sort filters        | Pruebas cubren filtros orden y búsqueda                     |
| XSS           | Escapar salida y limitar HTML                                       | Blade {{ }} y evitar {!! !!} con datos no confiables                 | Campos libres no ejecutan scripts                           |
| Archivos      | Subidas privadas y validadas                                        | Storage private mimes size random name antivirus opcional            | No existe URL pública directa a evidencia sensible          |
| Rate limit    | Limitar login API e importaciones                                   | RateLimiter throttle middleware                                      | Ataques repetidos reciben bloqueo temporal                  |
| Errores       | No exponer trazas en producción                                     | APP_DEBUG false logs sanitizados                                     | Errores muestran mensaje controlado sin SQL ni secretos     |
| Auditoría     | Registrar cambios críticos                                          | Observers y AuditLog service                                         | Reserva cierre falla aprobación y facturación dejan rastro  |
| Backups       | Backup cifrado y restauración probada                               | Jobs programados storage seguro y prueba mensual                     | Existe evidencia de restore exitoso                         |

## Mapa OWASP aplicado

| **Riesgo OWASP**               | **Aplicación en OPS BIOMED**                                               | **Control requerido**                                            |
|--------------------------------|----------------------------------------------------------------------------|------------------------------------------------------------------|
| Control de acceso roto         | Usuario ve casos facturas aprobaciones o inventario que no le corresponden | Policies por módulo y pruebas de permisos por rol                |
| Configuración insegura         | APP_DEBUG activo permisos de storage abiertos o CORS amplio                | Checklist de despliegue y revisión de env                        |
| Cadena de suministro           | Paquetes Composer o Node vulnerables                                       | composer audit npm audit lockfiles y CI                          |
| Fallas criptográficas          | Datos de paciente o evidencia sin cifrado                                  | HTTPS cifrado en reposo para campos sensibles y backups cifrados |
| Inyección                      | Filtros reportes o importaciones ejecutan entrada maliciosa                | Bindings listas blancas y validación estricta                    |
| Diseño inseguro                | Permitir cierre sin consumo o reserva sin stock                            | Reglas de dominio transaccionales y pruebas de negocio           |
| Autenticación débil            | Cuentas compartidas o usuarios inactivos                                   | Usuarios nominales MFA bloqueo y auditoría                       |
| Integridad de software y datos | Importación modifica stock sin revisión                                    | Staging preview aprobación y commit transaccional                |
| Logging insuficiente           | No se sabe quién aprobó costo cero o bloqueó material                      | Audit logs inmutables para eventos críticos                      |
| SSRF y accesos externos        | Campos de evidencia con enlaces externos peligrosos                        | Validar enlaces no descargar URLs internas y usar storage propio |

## Protección de datos sensibles

- Guardar solo datos del paciente necesarios para trazabilidad operativa y documental.

- Cifrar campos sensibles como documento identidad contacto notas y datos que no deban exponerse en reportes.

- Separar evidencia clínica o documentos firmados en storage privado con permisos por rol.

- Evitar datos de paciente en logs notificaciones correos y mensajes de error.

- Usar identificador interno del caso en dashboards cuando no sea necesario mostrar nombre completo.

- Definir retención y eliminación según política interna y normativa aplicable.

- Registrar acceso y descarga de documentos sensibles.

## Roles permisos y aprobaciones

| **Rol**                 | **Puede hacer**                                                    | **No debe hacer sin aprobación**             |
|-------------------------|--------------------------------------------------------------------|----------------------------------------------|
| Administrador           | Gestionar usuarios permisos catálogos y parámetros                 | Editar consumos cerrados sin auditoría       |
| Gerencia                | Ver dashboard aprobar excepciones mayores y desbloqueos críticos   | Modificar stock operativo directo            |
| Jefe de Línea           | Aprobar costo cero canje cesión y priorización operativa           | Eliminar evidencia o borrar casos            |
| Dirección Técnica       | Validar vencimientos fallas seguridad y liberación técnica         | Autorizar salida comercial o deuda           |
| Programador quirúrgico  | Crear casos programar reservar y reprogramar                       | Cerrar consumo o aprobar costo cero          |
| Almacén                 | Preparar despachar recibir inspeccionar y actualizar movimientos   | Reservar sin caso o liberar fallas           |
| Instrumentista          | Confirmar preoperatorio registrar consumo incidencias y evidencias | Modificar precio facturación o stock maestro |
| Comercial               | Ver cuentas médicos oportunidades cesiones y seguimiento           | Alterar consumos inventario o conciliación   |
| Administración cobranza | Gestionar OC factura abonos deuda y alertas                        | Cambiar consumo clínico u operativo          |
| Consulta auditoría      | Ver reportes según alcance                                         | Editar registros                             |

## Importador de catálogo Excel

El catálogo oficial se importa en dos etapas. Primero entra a una tabla staging sin alterar inventario. Luego se valida, se muestra un resumen de errores y recién se confirma la actualización.

1\. Subir archivo Excel y registrar usuario fecha hash del archivo y fuente.

2\. Leer columnas originales sin cambiar el Producto Código.

3\. Normalizar nombres longitudes diámetros tipo de corte almacén lote serie y vencimiento.

4\. Detectar códigos duplicados variantes aliases cantidades negativas vencidos desvalorizados y YSAN.

5\. Calcular stock elegible inmediato y stock disponible por YSAN a 48 horas.

6\. Mostrar previsualización de cambios y errores antes de aplicar.

7\. Confirmar importación con transacción y crear audit log.

| **Validación**        | **Resultado esperado** | **Acción ante error**                                        |
|-----------------------|------------------------|--------------------------------------------------------------|
| Producto Código vacío | Registro rechazado     | Corregir catálogo origen                                     |
| Cantidad negativa     | Alerta de conciliación | Bloquear como no elegible hasta investigar                   |
| Almacén desvalorizado | No elegible            | Mantener visible para control pero excluir de disponibilidad |
| YSAN                  | Disponible 48 h        | No permitir reserva inmediata sin confirmación logística     |
| Vencimiento cercano   | Pendiente DT           | DT valida y almacén ejecuta decisión                         |
| Código variante       | Alias sugerido         | Solo validar manualmente antes de unir                       |

## Reservas y stock elegible

La reserva debe ejecutarse dentro de una transacción de base de datos. El sistema calcula elegibilidad, bloquea cantidades y evita que dos casos tomen el mismo lote o equipo al mismo tiempo.

stock_elegible = principal_apto + consignacion_disponible - reservas_activas - bloqueados - vencidos - cuarentena - diferencias_pendientes

| **Semáforo** | **Condición**                                     | **Acción**                                              |
|--------------|---------------------------------------------------|---------------------------------------------------------|
| Verde        | Cinco o más cirugías completas por combinación    | Operación permitida y forecast normal                   |
| Amarillo     | Cuatro cirugías completas                         | Operación permitida con alerta preventiva               |
| Naranja      | Tres cirugías completas                           | Operación permitida con reposición prioritaria          |
| Rojo         | Una o dos cirugías completas                      | Escalar a Jefe de Línea y Comercial                     |
| Crítico      | Cero o cirugía programada sin combinación crítica | Bloquear confirmación o exigir aprobación de emergencia |

## Alertas automáticas

| **Alerta**            | **Disparador**                                   | **Destinatarios**                               | **Canal inicial**                   |
|-----------------------|--------------------------------------------------|-------------------------------------------------|-------------------------------------|
| Ruptura crítica       | Stock menor a mínimo 3 por combinación exacta    | Jefe de Línea Almacén Comercial Gerencia        | Dashboard correo WhatsApp operativo |
| Caso incompleto       | Cirugía en próximas 24 h sin reserva o checklist | Programador Almacén Instrumentista              | Dashboard correo                    |
| YSAN requerido        | Kit depende de stock YSAN                        | Almacén Jefe de Línea Logística                 | Correo tarea interna                |
| Vencimiento           | Producto por vencer o vencido                    | DT Almacén Jefe de Línea                        | Dashboard correo                    |
| Falla técnica         | Reporte con severidad media o alta               | DT Jefe de Línea Almacén Gerencia               | Correo alerta                       |
| Equipo no recogido    | Más de 24 h posterior a cirugía                  | Logística Almacén Jefe de Línea                 | Dashboard correo                    |
| Diferencia inventario | Enviado usado devuelto no concilia               | Almacén Jefe de Línea Administración            | Dashboard tarea                     |
| Factura pendiente     | Caso conciliado sin factura u OC                 | Administración Comercial Jefe de Línea          | Dashboard correo                    |
| Deuda vencida         | Días vencidos según política                     | Administración Comercial Jefe de Línea Gerencia | Dashboard correo                    |

## Dashboard inicial

| **Vista**         | **Indicadores**                                           | **Uso**                                       |
|-------------------|-----------------------------------------------------------|-----------------------------------------------|
| Operación diaria  | Cirugías de hoy mañana estado responsable hora riesgo     | Coordinar atención y resolver bloqueos        |
| Ruptura de stock  | Combinaciones críticas semáforo disponible reservado YSAN | Decidir compra canje préstamo o priorización  |
| Casos abiertos    | Pendiente cierre conciliación facturación evidencia       | Cerrar fugas operativas                       |
| Fallas            | Equipo aditamento lote estado días bloqueado recurrencia  | Control técnico y reposición                  |
| Facturación deuda | Monto pendiente OC factura abonos días vencidos           | Cobranza y escalamiento                       |
| Comercial         | Uso por médico institución consumo oportunidad cesión     | Crecimiento de cuenta y colocación de equipos |
| Productividad     | Casos por instrumentista tiempos cumplimiento incidencias | Mejorar cobertura y asignación                |

## API y endpoints internos

| **Endpoint**                 | **Método** | **Función**                                | **Seguridad**                            |
|------------------------------|------------|--------------------------------------------|------------------------------------------|
| /cases                       | GET POST   | Listar y crear casos                       | auth policy rate limit                   |
| /cases/{id}                  | GET PATCH  | Ver y actualizar caso                      | policy audit                             |
| /cases/{id}/reserve          | POST       | Crear reserva transaccional                | policy lock stock audit                  |
| /cases/{id}/preop            | POST       | Guardar checklist preoperatorio            | policy required fields audit             |
| /cases/{id}/consumption      | POST       | Registrar consumo evidencia y adicionales  | policy file validation audit             |
| /cases/{id}/return           | POST       | Registrar retorno inspección y diferencias | policy warehouse role audit              |
| /catalog/imports             | POST       | Subir catálogo Excel a staging             | admin almacén validation private storage |
| /catalog/imports/{id}/commit | POST       | Aplicar importación                        | approval transaction audit               |
| /failures                    | GET POST   | Registrar fallas bloqueos y seguimiento    | DT Jefe Línea policy                     |
| /billing/{case}              | POST PATCH | Registrar factura OC abono deuda           | Administración cobranza policy           |
| /dashboard/ops               | GET        | Indicadores operativos                     | auth scoped queries                      |

## Infraestructura local y despliegue

- Ambientes separados: local desarrollo prueba piloto y producción.

- Repositorio Git privado con ramas main develop y ramas por funcionalidad.

- Variables sensibles solo en archivo env del servidor y nunca en el repositorio.

- Servidor local con Nginx o Apache PHP FPM base de datos Redis opcional y supervisor para colas.

- APP_DEBUG false en producción APP_ENV production y logs con rotación.

- HTTPS obligatorio si se accede fuera de localhost o red interna controlada.

- Backups diarios de base de datos y storage con prueba mensual de restauración.

- Migraciones versionadas seeders controlados y rollback probado antes de piloto.

## Plan de pruebas

| **Tipo de prueba** | **Qué valida**                             | **Ejemplos**                                                 |
|--------------------|--------------------------------------------|--------------------------------------------------------------|
| Unitarias          | Reglas de negocio aisladas                 | Stock elegible semáforo YSAN vencimiento desvalorizado       |
| Feature            | Flujos completos por módulo                | Crear caso reservar cerrar consumo conciliar                 |
| Permisos           | Acceso por rol                             | Instrumentista no modifica factura Comercial no libera falla |
| Importación        | Excel staging validación commit            | Duplicados negativos vencidos aliases                        |
| Seguridad          | CSRF XSS SQL injection archivos rate limit | Payloads maliciosos no ejecutan ni alteran datos             |
| Auditoría          | Eventos críticos quedan trazados           | Costo cero bloqueo falla reserva cierre factura              |
| Restauración       | Backup recupera datos                      | Restore mensual y evidencia                                  |
| UAT piloto         | Uso real del equipo operativo              | Tres a cinco cirugías simuladas y una semana real            |

## Backlog por fases

| **Fase** | **Entregable**                                                     | **Cierre**                                  |
|----------|--------------------------------------------------------------------|---------------------------------------------|
| Fase 0   | Repositorio Laravel autenticación roles permisos auditoría base    | Usuarios ingresan y permisos se prueban     |
| Fase 1   | Catálogo inventario importador Excel reglas de kit                 | Dashboard muestra stock elegible y ruptura  |
| Fase 2   | Casos quirúrgicos programación reservas y checklists               | Caso real puede reservar y avanzar estados  |
| Fase 3   | Consumo retorno conciliación fallas y bloqueos                     | Caso cierra con evidencia o queda observado |
| Fase 4   | Facturación cobranza excepciones costo cero canje cesiones         | Valorizado y deuda quedan visibles          |
| Fase 5   | Dashboard ejecutivo forecast productividad y seguimiento comercial | Gerencia ve riesgo venta y oportunidad      |
| Fase 6   | Hardening seguridad pruebas backup y piloto formal                 | Aprobación para operación continua          |

## Criterios de aceptación del MVP

- El sistema permite importar el catálogo completo sin perder códigos oficiales.

- El sistema clasifica stock inmediato stock YSAN 48 h stock no elegible y stock observado.

- Un caso no puede reservar material crítico inexistente sin excepción aprobada.

- Un usuario solo ve y ejecuta acciones permitidas por su rol.

- Toda reserva consumo retorno falla aprobación y facturación genera auditoría.

- Todo caso puede cerrarse solo con consumo retorno conciliación y facturación o sustento de no facturación.

- El dashboard identifica combinaciones por debajo de mínimo 3 y objetivo 5.

- La información sensible de paciente evidencia y documentos no queda expuesta públicamente.

- El sistema permite operar una prueba piloto con casos reales durante una semana sin hojas paralelas obligatorias.

## Riesgos críticos y controles

| **Riesgo**                  | **Impacto**                                               | **Control**                                            |
|-----------------------------|-----------------------------------------------------------|--------------------------------------------------------|
| Catálogo mal importado      | Stock falso y ruptura en cirugía                          | Staging validación preview y aprobación                |
| Permisos débiles            | Exposición de pacientes facturas o cambios no autorizados | RBAC policies y pruebas automáticas                    |
| Cierre incompleto           | Fuga de facturación y pérdida de trazabilidad             | Workflow con bloqueos y estados obligatorios           |
| YSAN contado como inmediato | Promesa operativa imposible                               | Lead time 48 h y bandera no inmediato                  |
| Falla no bloqueada          | Riesgo de uso inseguro                                    | Reporte obligatorio y bloqueo preventivo por severidad |
| Archivos públicos           | Exposición documental                                     | Storage privado y links temporales                     |
| Deuda no visible            | Atención sin control financiero                           | Alertas y escala automática                            |
| Sin backup probado          | Pérdida total de operación                                | Backup diario y restore mensual                        |

## Definición de terminado para desarrollo

- Migración creada y probada.

- Modelo relaciones casts enums y políticas implementadas.

- Form Request con validación completa.

- Pruebas unitarias y feature mínimas aprobadas.

- Permisos por rol probados.

- Auditoría registrada en eventos críticos.

- Errores controlados sin exponer datos sensibles.

- Vista usable en escritorio y celular.

- Documentación técnica actualizada.

## Referencias técnicas

OWASP Top Ten Web Application Security Risks. https://owasp.org/www-project-top-ten/

OWASP Laravel Cheat Sheet. https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html

Laravel CSRF Protection. https://laravel.com/framework/docs/csrf

Laravel framework documentation. https://laravel.com/docs

## Próximos pasos

1\. Confirmar servidor local objetivo PHP base de datos y acceso de red.

2\. Crear repositorio OPS BIOMED y levantar Laravel con autenticación.

3\. Cargar seeders de roles permisos tipos de cirugía almacenes estados y reglas de kit.

4\. Construir importador Excel del catálogo con tabla staging.

5\. Construir módulo de casos quirúrgicos y reserva transaccional.

6\. Ejecutar piloto con casos históricos y luego una semana de operación real.
