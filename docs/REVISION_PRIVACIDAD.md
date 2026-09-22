# Revision de privacidad para QA local

Fecha: 2026-09-20. Revision tecnica preliminar con cuentas y datos ficticios. No es dictamen legal ni declaracion de cumplimiento normativo.

## Controles observados

- Backup sin `.env`; SQL y storage contienen centinelas sinteticos. El archivo privado se restaura con hash comprobado.
- Logs de las copias locales revisadas: no se hallaron patrones de APP_KEY, bearer token ni password de piloto.
- El piloto usa `Paciente Demo MR8`; smoke y reporte priorizan acceso por roles. No se adjunto informacion clinica real.
- Documentos privados requieren policy y descarga autenticada. Durante esta fase se cerro el acceso de Comercial a facturas/OC/pagos incluso cuando estan relacionados a un caso; los tests verifican lista, detalle y descarga por ID.
- Dashboard/estado comercial no muestra importes a Comercial; billing.update no se concede a ese rol.
- La politica de IA prohíbe enviar datos personales/salud identificables a IA externa sin autorizacion; no se envio informacion a proveedores IA durante QA.

## Brechas y acciones

- Verificar retencion, borrado logico, restauracion, acceso de soporte y copias fuera del equipo con el responsable de privacidad.
- Confirmar minimizacion de nombres de paciente en exportes/reportes y necesidad operativa de cada campo.
- Documentar base legal, informacion a titulares, encargados, transferencias y respuesta a derechos con asesor legal; no inferir cumplimiento a partir de estas pruebas.
- Revisar instalacion real del almacenamiento privado, permisos web-server y logs del hosting antes de cargar informacion identificable.
- Repetir smoke con Comercial/Cobranza y documentos reales anonimizados solo despues de aprobacion interna.
