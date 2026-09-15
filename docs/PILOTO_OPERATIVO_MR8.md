# Piloto operativo OPS BIOMED MR8

## Carga del escenario

Ejecutar desde `C:\laragon\www\ops-biomed`:

```powershell
php artisan db:seed --class=PilotDemoSeeder
```

El seeder es idempotente. Usa codigos y lotes con prefijo `PILOT` y no elimina datos existentes.

## Usuarios piloto

Todos usan la contrasena temporal definida localmente en `PILOT_DEMO_PASSWORD`.
Este valor no se versiona. Cambiarlo antes de usar el ambiente con informacion real.

| Correo | Rol |
| --- | --- |
| `admin@ops.test` | Administrador |
| `jefe.linea@ops.test` | Jefe de Linea |
| `dt@ops.test` | Direccion Tecnica |
| `almacen@ops.test` | Almacen |
| `instrumentista@ops.test` | Instrumentista |
| `comercial@ops.test` | Comercial |
| `cobranza@ops.test` | Cobranza |
| `gerencia@ops.test` | Gerencia |

Cambiar las contrasenas antes de usar el ambiente con informacion real.

## URLs principales

- `/dashboard/ops`
- `/cases`
- `/cases/MR8-PILOT-001` (abrir el detalle desde el listado)
- `/inventory`
- `/inventory/coverage`
- `/inventory/forecast`
- `/catalog/imports`
- `/failures`
- `/returns`
- `/documents`
- `/billing`
- `/approvals/cost-zero`
- `/reports`
- `/masters/institutions`
- `/masters/doctors`
- `/masters/prices`
- `/admin/users`

## Datos demo disponibles

- Institucion: `Clínica Piloto OPS`.
- Medico: `Dr. Piloto Neurocirugía`.
- Paciente: `Paciente Demo MR8`.
- Caso operativo: `MR8-PILOT-001`, programado para manana a las 08:00 y con estado `reservado` despues de crear dos reservas.
- Caso administrativo: `MR8-PILOT-CIERRE-001`, cerrado, conciliado y con costo cero pendiente de aprobacion.
- Productos demo: `MR8-9BA30`, `MR8-9BA30D`, `MR8-10BA20`, `MR8-10BA20D`, `MR8-10BA30`, `MR8-10BA30D`, `MR8-F2/7TA23`, `MR8-F3/9TA30` e `IRD300`.
- Inventario: lotes verde, amarillo, rojo, YSAN, desvalorizado, bloqueado, cuarentena, proximo a vencer y vencido.
- Evidencia: solicitud del caso principal registrada como link y pendiente de validacion.
- Falla: vibracion de severidad alta, bloqueada preventivamente.
- Devolucion: pendiente de inspeccion.
- Cobranza: registro pendiente de orden de compra.

## Flujo por rol

### Administrador

1. Iniciar sesion con `admin@ops.test`.
2. Revisar `/dashboard/ops` y confirmar agenda, cobertura, forecast, falla, devolucion, costo cero y cobranza.
3. Abrir el caso `MR8-PILOT-001` y revisar las dos reservas activas y la evidencia pendiente.
4. Revisar `/admin/users` para confirmar los ocho usuarios y sus roles.

Resultado esperado: acceso a todos los modulos y trazabilidad completa.

### Jefe de Linea

1. Abrir `/cases` y revisar el caso principal.
2. Revisar `/inventory/coverage` y `/inventory/forecast`.
3. Consultar `/reports` y la aprobacion de costo cero.
4. No debe gestionar fallas tecnicas de liberacion.

Resultado esperado: puede operar solicitudes, reservas, cobertura, forecast, reportes y aprobaciones segun sus permisos.

### Almacen

1. Abrir `/inventory` y revisar los estados de los lotes PILOT.
2. Confirmar que YSAN no aparece como disponibilidad inmediata.
3. Abrir `/returns` y revisar la devolucion pendiente de inspeccion.
4. Revisar `/cases` y la reserva incompleta del caso principal.

Resultado esperado: puede revisar inventario, reservas e inspeccion fisica; no edita facturacion.

### Instrumentista

1. Abrir el detalle de `MR8-PILOT-001`.
2. Revisar material reservado, evidencia y acceso a cierre.
3. Revisar fallas y documentos relacionados.

Resultado esperado: puede registrar consumo, evidencias y fallas desde la operacion, sin administrar precios o cobranza.

### Direccion Tecnica

1. Abrir `/failures` y revisar la falla bloqueada del lote `PILOT-BLOQUEADO-10-3-D`.
2. Abrir `/returns` y evaluar la devolucion pendiente.
3. Revisar `/inventory` para confirmar que el lote bloqueado no es elegible.

Resultado esperado: puede hacer revision tecnica y liberar o mantener bloqueos segun evidencia.

### Comercial

1. Abrir `/cases` y `/reports/commercial`.
2. Consultar `/masters/institutions`, `/masters/doctors` y `/masters/prices`.
3. Revisar el caso y su institucion/médico asociado.

Resultado esperado: ve disponibilidad y gestion comercial, sin acceso al forecast interno detallado ni a la cobranza operativa.

### Cobranza

1. Abrir `/billing`.
2. Revisar el caso principal con estado `pendiente_oc`.
3. Abrir `/reports/billing` y confirmar el monto pendiente.

Resultado esperado: puede actualizar OC, factura y pago con las validaciones existentes.

### Gerencia

1. Abrir `/dashboard/ops` y `/reports`.
2. Revisar `/approvals/cost-zero`.
3. Aprobar o rechazar el caso `MR8-PILOT-CIERRE-001` segun la politica del piloto.

Resultado esperado: ve indicadores ejecutivos y puede aprobar costo cero, pero no opera inventario fisico.

## Validaciones del dashboard

En `/dashboard/ops` debe ser visible, segun los permisos del usuario:

- Cirugia de manana dentro de proximas 48 horas.
- Caso pendiente de completar reservas.
- Falla abierta/bloqueada y lote bloqueado por falla.
- Devolucion pendiente de inspeccion.
- Documento cargado pendiente de validacion.
- Costo cero pendiente de aprobacion.
- Registro de cobranza pendiente de orden de compra.

## Validaciones de cobertura y forecast

En `/inventory/coverage`:

- Verde: al menos una combinacion con stock neto de 5 o mas.
- Amarillo: combinaciones con stock neto de 3 o 4.
- Rojo: combinaciones con menos de 3.
- El lote YSAN no aumenta la cobertura inmediata.
- El lote desvalorizado, bloqueado, en cuarentena y vencido no se cuenta.

En `/inventory/forecast`:

- Los items rojos aparecen con urgencia critica y compra urgente.
- Los amarillos aparecen con urgencia alta y compra para objetivo.
- YSAN aparece como respaldo externo, no como stock inmediato.
- Las reservas activas reducen el stock neto.

## Validaciones de cierre y devolucion

1. Desde `MR8-PILOT-001`, entrar a cierre.
2. Confirmar que cada reserva exige destino: usado, abierto no usado, devuelto o falla.
3. Probar la conciliacion dejando una diferencia y confirmar que solicita motivo.
4. Revisar `/returns` y confirmar que el retorno demo esta en `pendiente_inspeccion`.
5. No liberar el lote de devolucion sin una inspeccion valida.

## Validaciones de facturacion y reportes

- `/billing`: el caso principal queda pendiente de OC y tiene saldo pendiente.
- `/approvals/cost-zero`: el caso de cierre aparece como `pendiente_aprobacion`.
- `/reports/billing`: refleja el registro pendiente.
- `/reports/operations`: refleja los casos demo, falla y cierre.
- `/reports/inventory`: refleja riesgos, lotes vencidos/proximos a vencer y bloqueos.
- `/reports/commercial`: refleja la institucion y el medico piloto.

## Limpieza

No se debe borrar todo el ambiente para repetir el piloto. Reejecutar `PilotDemoSeeder` actualiza unicamente los registros identificados por correos, codigos de caso, codigos de producto y lotes `PILOT`.
