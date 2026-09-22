# Respuesta a incidentes

Completar contactos, suplentes, hosting y responsables antes del piloto. No incluir pacientes, secretos ni evidencia sensible en canales no aprobados.

## Severidad operativa

- **P0:** exposicion confirmada de datos sensibles, acceso no autorizado, corrupcion activa del inventario o indisponibilidad con impacto quirurgico inmediato.
- **P1:** inconsistencia de reserva/consumo, despliegue fallido, backup no recuperable o interrupcion importante.
- **P2:** modulo degradado, evidencia inaccesible, alertas atrasadas o permisos incorrectos sin exposicion confirmada.
- **P3:** defecto visual/documental sin impacto inmediato.

## Objetivos de continuidad

- RPO maximo aprobado: 1 hora.
- RTO maximo aprobado: 2 horas.
- Estos objetivos aun no han sido probados en el hosting; no comunicar que estan alcanzados hasta medir una restauracion en staging.
- Backup no valido hasta verificar checksum y completar restauracion de base y evidencia privada.

## Pasos de respuesta

1. Contener sin borrar registros ni evidencia; pausar la accion y escalar cualquier riesgo fisico/inventario a Almacen y Direccion Tecnica.
2. Notificar a responsable operativo, administrador tecnico y responsable de privacidad segun el incidente.
3. Conservar IDs, audit IDs, lote, release SHA y marcas de tiempo; redactar datos personales, tokens y secretos.
4. Evaluar auditoria y estado de DB; no corregir datos productivos directamente sin autorizacion.
5. Revertir codigo solo si el esquema sigue siendo compatible. Para perdida de datos, usar backup verificado con aprobacion explicita.
6. Validar reservas, stock fisico, devoluciones, cierre, conciliacion, billing, documentos y alertas antes de reanudar.
7. Registrar causa, impacto, responsable, acciones y evidencia, evitando datos de salud en el registro.

## Escenarios operativos

- Inventario/reserva dudosa: pausar asignacion del lote, revisar trazabilidad y contar fisicamente.
- Falla o devolucion: mantener bloqueo/cuarentena hasta inspeccion/liberacion autorizada.
- Evidencia expuesta: restringir acceso, conservar auditoria y no mover a un canal publico.
- Despliegue fallido: restaurar el puntero al release previo; no revertir migraciones ni restaurar DB automaticamente.
- Scheduler sin ejecucion por mas de 70 minutos: avisar a TI y responsable operativo; correr evaluacion manual solo en entorno autorizado y registrar el resultado.

## Datos por completar

On-call, suplentes, telefono del hosting, DBA, privacidad, responsable del negocio, canal seguro, destino offsite, retencion de logs y procedimiento de comunicacion a instituciones. Registrar quien acepta una eventual perdida de escrituras posterior al backup.
