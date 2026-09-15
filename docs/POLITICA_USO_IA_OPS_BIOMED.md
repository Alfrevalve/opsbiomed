# Politica de Uso de IA - OPS BIOMED MR8

**Version:** 1.0
**Ultima actualizacion:** 11 de septiembre de 2026
**Responsables internos:** Gerencia y Administrador de OPS BIOMED

## 1. Finalidad y alcance

Esta politica establece los controles internos para cualquier funcion presente o futura de inteligencia artificial (IA) en OPS BIOMED MR8. El sistema es una Torre de Control Quirurgica para apoyo operativo, logistica, trazabilidad, inventario, documentacion, facturacion y reportes de la linea Midas Rex MR8.

OPS BIOMED no usa IA como sistema de diagnostico, pronostico, indicacion medica, triaje, decision clinica autonoma ni sustituto del juicio profesional. Toda decision clinica, tecnica, comercial, administrativa o financiera continua bajo responsabilidad de una persona autorizada.

Esta politica es un control operativo interno orientado a la conformidad con la Ley N.° 31814, su Reglamento aprobado por DS N.° 115-2025-PCM y la Ley N.° 29733. No reemplaza la evaluacion legal o regulatoria especifica que pudiera requerir una nueva funcion de IA.

## 2. Usos permitidos

Las funciones asistidas por IA solo pueden proponerse para apoyo operativo y siempre con revision humana, por ejemplo:

- Resumir informacion operativa ya autorizada.
- Preparar borradores de reportes, comunicaciones internas o listas de seguimiento.
- Detectar inconsistencias en datos agregados, desidentificados o no sensibles.
- Sugerir prioridades de inventario, cobertura, forecast o documentacion sin ejecutar acciones.
- Clasificar borradores para que un usuario autorizado los revise y decida.

Toda salida debe mostrarse con la etiqueta **"Sugerencia asistida"** y permitir al usuario humano aceptarla, corregirla o descartarla.

## 3. Usos prohibidos

Se prohibe usar IA para:

- Emitir diagnosticos, pronosticos, indicaciones terapeuticas o recomendaciones clinicas.
- Tomar decisiones autonomas sobre cirugias, pacientes, prioridad clinica, tratamiento o acceso a salud.
- Cerrar casos, aprobar costos cero, liberar fallas, liberar inventario, facturar, registrar pagos o ejecutar cualquier accion operativa sin confirmacion humana expresa.
- Evaluar o perfilar pacientes, personal o cuentas de forma discriminatoria o sin finalidad operativa legitima.
- Enviar datos personales sensibles identificables a una IA externa sin la autorizacion, base legal, controles de privacidad, seguridad y transferencia que correspondan.
- Persistir prompts o respuestas que contengan datos sensibles identificables en las bitacoras de IA.

## 4. Clasificacion preliminar de riesgo

Los usos internos actuales previstos se clasifican preliminarmente como **riesgo aceptable** cuando se limitan a apoyo operativo, no determinan resultados sobre personas y no procesan datos personales sensibles identificables.

Esta clasificacion debe revisarse antes de cualquier despliegue que:

- procese datos de salud, historias clinicas, imagenes o identificadores de pacientes;
- influya en una decision clinica, tecnica de seguridad o de acceso a servicios de salud;
- use un proveedor externo o transfiera datos fuera de los entornos autorizados; o
- automatice decisiones con efectos materiales sobre personas, inventario, facturacion o cumplimiento.

En esos casos se requiere una evaluacion especifica de riesgo, privacidad, seguridad, sesgo, proveedores, controles de supervision humana y aprobacion de Gerencia antes de habilitar la funcion.

## 5. Supervision humana y rendicion de cuentas

Ninguna sugerencia de IA es autoejecutable. Un usuario autorizado debe:

1. revisar los datos y el contexto;
2. verificar que no se use para una decision prohibida;
3. aceptar, corregir o descartar la sugerencia; y
4. asumir la decision final y la accion correspondiente.

La supervision humana puede detener, modificar, invalidar o no usar cualquier sugerencia. Si la sugerencia afecta inventario, seguridad tecnica, facturacion, cobranza o documentos, se mantienen los permisos, validaciones, transacciones y auditorias existentes de OPS BIOMED.

## 6. Proteccion de datos personales y de salud

OPS BIOMED debe aplicar minimizacion de datos, control de acceso, finalidad definida y seguridad proporcional. Queda prohibido ingresar en servicios externos no autorizados nombres de pacientes, DNI, historias clinicas, diagnosticos, imagenes, resultados medicos, ubicaciones precisas, contactos, informacion financiera identificable u otros datos personales sensibles.

Antes de usar un proveedor externo se debe confirmar, como minimo:

- finalidad, base legal y autorizacion interna aplicable;
- necesidad de los datos y posibilidad de usar datos agregados o desidentificados;
- controles contractuales, de seguridad, retencion y transferencias de datos;
- restricciones de acceso, revisiones de proveedor e incidentes; y
- aprobacion de Gerencia y del responsable de proteccion de datos, cuando corresponda.

## 7. Trazabilidad y auditoria

La tabla `ai_usage_logs` registra solo metadatos minimos: usuario, modulo, accion, tipo de entrada, indicadores de datos personales o de salud, proveedor, requisito de revision humana y resultado de la revision. No guarda prompts, respuestas ni contenido sensible.

Toda futura funcion asistida debe usar la bitacora y conservar evidencia de que la sugerencia fue revisada por una persona. Las acciones finales continuan registrandose con la auditoria operativa existente de OPS BIOMED.

## 8. Responsables internos

- **Gerencia:** aprueba la politica, prioriza controles y autoriza cambios de alcance relevantes.
- **Administrador de OPS BIOMED:** gestiona permisos, configuracion tecnica, accesos y trazabilidad de la plataforma.
- **Jefe de Linea y Direccion Tecnica:** validan que las sugerencias operativas o tecnicas no sustituyan decisiones profesionales ni controles de seguridad.
- **Usuarios operativos:** no ingresan datos sensibles a herramientas no autorizadas y revisan toda sugerencia antes de actuar.

## 9. Principios de aplicacion

- Transparencia.
- Supervision humana.
- Proteccion de datos.
- No discriminacion.
- Seguridad.
- Trazabilidad.
- Rendicion de cuentas.

## 10. Marco normativo de referencia

- [Ley N.° 31814, Ley que promueve el uso de la inteligencia artificial](https://www.gob.pe/institucion/congreso-de-la-republica/normas-legales/4565760-31814).
- [DS N.° 115-2025-PCM, Reglamento de la Ley N.° 31814](https://busquedas.elperuano.pe/dispositivo/NL/2436426-1).
- [Ley N.° 29733, Ley de Proteccion de Datos Personales](https://leyes.congreso.gob.pe/documentos/leyes/29733.pdf).

La politica se revisara antes de habilitar una funcion de IA, ante un cambio material de proveedor, datos, finalidad o riesgo, y al menos una vez por periodo anual.
