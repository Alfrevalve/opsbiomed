# Arquitectura de OPS BIOMED MR8

## Stack

- Laravel Framework 12.69.2, PHP `^8.3`; entorno local Laragon usa PHP 8.3.33 y MySQL 8.4.3.
- `composer.json` restringe Laravel a `^12.0`; Laravel 13 requerira evaluacion y migracion separadas.
- Blade/Breeze 2.4.2, Vite 6 y Tailwind CSS 4 con `@tailwindcss/vite`.
- Livewire 3.8.8 esta instalado; Inertia, Vue y React no son el stack de interfaz.
- Spatie Laravel Permission 6.25.0 y Maatwebsite Excel 3.1.70.
- PHPUnit usa SQLite para la suite normal. Concurrencia InnoDB se prueba aparte en MySQL desechable `ops_biomed_test`.

## Capas

1. `routes/web.php`: flujos autenticados de casos, agenda, inventario, catalogo, billing, documentos, fallas, devoluciones, maestros y reportes.
2. Controladores HTTP orquestan Requests, Policies y vistas Blade.
3. `app/Http/Requests` valida entradas y permisos; no confiar en controles de interfaz.
4. `app/Services` concentra transacciones, bloqueos, reglas operativas y auditoria.
5. Eloquent, migraciones y seeders mantienen esquema, roles y permisos.
6. `AuditLogger` registra usuario, accion, entidad, before/after, IP y user agent. Evidencias operativas usan disco privado.

## Procesos y continuidad

- `routes/console.php` programa poda diaria y evaluacion horaria de alertas en `America/Lima`, con `withoutOverlapping`. cPanel debe ejecutar `schedule:run` cada minuto.
- Notificaciones SLA actuales son internas a la aplicacion y almacenan solo identificadores, modulo y prioridad; no se activa envio externo.
- `ReservationReleaseService` gestiona liberacion total y cancelacion transaccional e idempotente. `reservation_release_operations` conserva resultado e idempotency key.
- `CatalogImportService` serializa modificaciones y reintenta transacciones ante deadlock hasta tres veces.
- Workflow remoto esta deshabilitado. Scripts de backup/deploy requieren staging, rutas exactas y modo dry-run por defecto.

## Limites

Esta es una descripcion del codigo local, no de infraestructura productiva ni certificacion. No se accedio a produccion/cPanel, no se midio RPO/RTO y no se hizo restore/rollback en staging. SQLite no sustituye pruebas MySQL de locks/deadlocks. IA y alertas apoyan operacion; la confirmacion humana sigue siendo obligatoria. No enviar datos identificables de salud a proveedores externos sin aprobacion.
