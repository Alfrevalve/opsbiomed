# Matriz de permisos

Fuente: `database/seeders/RoleAndPermissionSeeder.php`, policies, Form Requests y controladores actuales. Spatie Permission es el origen de asignación; esta matriz resume permisos de rol, no reemplaza la autorización por registro. El sidebar no es una barrera de seguridad.

| Rol | Acceso general observado | Límites sensibles |
|---|---|---|
| Administrador | Todos los permisos definidos por el seeder | Administración global |
| Gerencia | Dashboard, lectura de casos/inventario, auditoría, fallas, reportes, documentos y billing | Aprueba costo cero; sin `billing.update` ni `users.manage` |
| Jefe de Linea | Operación, agenda, reservas, fallas, reportes, documentos y maestros | Administra agenda/maestros, aprueba costo cero, ve billing; no actualiza pagos |
| Direccion Tecnica | Casos, inventario técnico, fallas, devoluciones, documentos, auditoría | Liberación técnica; no gestión de pagos |
| Almacen | Inventario, catálogo, reservas, devoluciones, fallas y documentos | Ajusta inventario e inspecciona físicamente; sin liberación técnica de fallas |
| Programador Quirurgico | Casos, reservas y maestros | Crea/actualiza solicitudes; sin permisos financieros |
| Instrumentista | Casos, cierre, agenda, trazabilidad, fallas y documentos | Cierra casos y carga evidencia; no gestiona stock/precios/billing |
| Comercial | Lectura comercial, casos, agenda, inventario, maestros y documentos | Sin `billing.view/update`, forecast interno ni gestión de pagos |
| Cobranza | Billing, documentos y reportes financieros | `billing.view/update`; sin gestión de inventario |
| Consulta Auditoria | Lectura de dashboard, casos, inventario, billing, auditoría y alertas | Solo lectura por seeder |

## Controles específicos

- `/admin/users/*`: middleware `can:users.manage`; requests/controlador también autorizan.
- Forecast/CSV: exige `inventory.view` y rol Administrador, Jefe de Linea, Direccion Tecnica, Almacen o Gerencia.
- Billing: mutación requiere `billing.update`; Comercial puede tener lectura comercial básica, pero montos se condicionan a `billing.view`.
- Documentos: Policies separan `view`, `upload`, `validate`, `delete`; la carga genérica también verifica permiso sobre entidad destino.
- Maestros/precios: permisos `masters.*` y `prices.*`; alertas, devoluciones y fallas separan lectura y acción.
- Manual: `manual.manage` más rol Administrador o Direccion Tecnica.

## Liberacion de reservas (Fase 3)

`reservations.release` se asigna solo a Administrador, Jefe de Linea y Almacen. Instrumentista, Comercial y usuarios sin permiso reciben 403. No se reutiliza `inventory.release`, que corresponde a liberar inventario por criterio tecnico.

La ruta transaccional permite liberacion total unicamente antes de despacho/custodia. Material despachado, internado, consumido, conciliado, devuelto o fallado debe completar devolucion e inspeccion. La cancelacion del caso es atomica y no cambia cantidades fisicas.

## Mantenimiento

Agregar pruebas de autorización positiva/negativa en cada nuevo endpoint, incluyendo acceso horizontal con IDs ajenos. Al cambiar roles, actualizar seeder idempotente y matriz; no confiar en controles Blade solamente.
