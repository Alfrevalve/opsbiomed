# OPS BIOMED MR8

Torre de control quirurgica para la operacion Midas Rex MR8 de Representaciones Medicas Biomed S.A.C.

OPS BIOMED centraliza solicitudes quirurgicas, agenda, reservas, inventario trazable, consumo, devoluciones, fallas tecnicas, evidencias, cobertura, forecast, valorizacion, facturacion, alertas SLA y reportes.

## Stack

- Laravel 12.69.2 con PHP 8.3.
- Blade y Laravel Breeze.
- Vite 6.4.3.
- Tailwind CSS 4.3.3 mediante `@tailwindcss/vite`.
- Livewire 3.8.8 instalado para componentes existentes; la interfaz principal usa Blade.
- MySQL para piloto y produccion; SQLite para pruebas automatizadas.
- Spatie Laravel Permission 6.25.0.
- Maatwebsite Excel 3.1 para importaciones controladas.

## Modulos principales

- `/dashboard/ops`: Torre de Control Quirurgica.
- `/cases`: solicitudes, reservas, cierre, conciliacion y control operativo.
- `/schedule`: agenda quirurgica y conflictos de recursos.
- `/inventory`: inventario, cobertura MR8 y forecast.
- `/catalog/imports`: staging, validacion y commit del catalogo Excel.
- `/failures`: fallas tecnicas, bloqueo, revision y liberacion.
- `/returns`: inspeccion de devoluciones postoperatorias.
- `/documents`: evidencias y documentos trazables.
- `/billing` y `/approvals/cost-zero`: valorizacion, facturacion, cobranza y costos cero.
- `/reports`: reportes operativos, comerciales, financieros e inventario.
- `/manual`: manual operativo.
- `/trace`: trazabilidad por codigo interno.
- `/alerts`: alertas SLA operativas.
- `/masters`: instituciones, medicos y precios.
- `/admin/users`: usuarios, roles y accesos.

## Requisitos

- PHP 8.3 o superior.
- Composer 2.
- Node.js 20 o superior y npm.
- MySQL 8 o PostgreSQL para el entorno de aplicacion.
- Extensiones PHP: `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath` y `zip`.
- Apache con `mod_rewrite` o un servidor compatible con Laravel.

La extension `zip` es necesaria para importar archivos Excel.

## Desarrollo local

Desde `C:\laragon\www\ops-biomed`:

```powershell
Copy-Item .env.laragon.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RoleAndPermissionSeeder
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8080
```

Completar `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env` antes de migrar. Para crear el usuario administrador local, definir `INITIAL_ADMIN_PASSWORD` en el `.env` ignorado y ejecutar:

```powershell
php artisan db:seed --class=InitialUserSeeder
```

Para cargar el escenario demo, definir `PILOT_DEMO_PASSWORD` solo en el `.env` local y ejecutar:

```powershell
php artisan db:seed --class=PilotDemoSeeder
```

El password demo no se almacena en el repositorio. El escenario esta documentado en [docs/PILOTO_OPERATIVO_MR8.md](docs/PILOTO_OPERATIVO_MR8.md).

## Pruebas

```powershell
php artisan optimize:clear
php artisan test --compact
composer validate
composer audit
npm ci
npm run build
php artisan view:cache
php artisan route:list --except-vendor
php artisan schedule:list
```

El proyecto usa PHPUnit. Las pruebas usan SQLite en memoria y no requieren datos del piloto.

## Scheduler y colas

El scheduler evalua alertas SLA cada hora y limpia batches diariamente:

```powershell
php artisan schedule:list
php artisan schedule:work
```

La configuracion por defecto usa cola de base de datos. En cPanel se deben programar ambos comandos cada minuto:

```cron
* * * * * cd /home/jesusvalera/ops-biomed && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/jesusvalera/ops-biomed && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

## Entornos

### Desarrollo

- `APP_ENV=local`.
- `APP_DEBUG=true` solo en la maquina local.
- Usar `.env.laragon.example` como punto de partida.
- No cargar datos reales de pacientes.

### Pruebas

- PHPUnit fuerza `APP_ENV=testing`, SQLite en memoria, cache array, sesion array y cola sync.
- `PILOT_DEMO_PASSWORD` en `phpunit.xml` es un valor de prueba, no una credencial operativa.

### Produccion

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL=https://opsbiomed.alfreval.com`.
- MySQL independiente, SMTP real, cookies seguras y storage privado.
- No ejecutar `PilotDemoSeeder` ni `DatabaseSeeder` automaticamente.
- Ejecutar unicamente seeders autorizados, por ejemplo `RoleAndPermissionSeeder`, sin sobrescribir usuarios ni datos operativos.

La plantilla esta en `.env.production.example`. Nunca subir `.env`, `APP_KEY`, credenciales, logs, respaldos, evidencias privadas ni datos de pacientes.

## Despliegue en GitHub y cPanel

La guia recomendada esta en [docs/DEPLOY_GITHUB_CPANEL.md](docs/DEPLOY_GITHUB_CPANEL.md). La guia general de cPanel esta en [docs/DEPLOY_PRODUCCION_OPS_BIOMED.md](docs/DEPLOY_PRODUCCION_OPS_BIOMED.md).

Document root obligatorio:

```text
/home/jesusvalera/ops-biomed/public
```

El proyecto no debe apuntar al directorio raiz `/home/jesusvalera/ops-biomed`.

El flujo recomendado es repositorio privado, build en GitHub Actions, despliegue manual mediante `workflow_dispatch` y SSH a cPanel. Los secretos viven solo en GitHub Secrets y en el `.env` del servidor.

## Rollback y seguridad

- Respaldar base de datos, `.env` y `storage/app/private` antes de cada despliegue.
- No usar `migrate:fresh`, `db:wipe`, `git reset --hard` ni `rm -rf` en produccion.
- Mantener `APP_DEBUG=false`, HTTPS, CSRF, rate limiting, policies y middleware de usuarios activos.
- Verificar `/up`, login, dashboard, colas, scheduler y acceso privado a documentos despues de desplegar.

Procedimiento detallado: [docs/ROLLBACK_GITHUB_CPANEL.md](docs/ROLLBACK_GITHUB_CPANEL.md).

## Documentacion de seguridad

- [docs/SECURITY_GITHUB.md](docs/SECURITY_GITHUB.md)
- [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md)
- [docs/POLITICA_USO_IA_OPS_BIOMED.md](docs/POLITICA_USO_IA_OPS_BIOMED.md)

## Licencia

Proyecto propietario de Representaciones Medicas Biomed S.A.C.
