# Despliegue de OPS BIOMED MR8 en cPanel

## Estado de esta preparación

- Aplicación: OPS BIOMED MR8.
- Dominio objetivo: `https://opsbiomed.alfreval.com`.
- Servidor objetivo: cPanel con Apache, PHP 8.3 y MySQL.
- Estado: preparación local y runbook listos; el despliegue remoto queda pendiente de acceso al cPanel, DNS, SSL y credenciales de base de datos.
- No se incluyen contraseñas, claves SMTP ni el `APP_KEY` real en este documento.

## Stack verificado localmente

- Laravel `12.69.2`.
- PHP `8.3.33`.
- Composer: requerido por `composer.json`; verificar el binario disponible en cPanel antes de instalar.
- Blade y Laravel Breeze para autenticación.
- Livewire `3.8.8` instalado como dependencia del proyecto; la interfaz principal usa Blade.
- Vite `6.4.3`.
- Tailwind CSS `4.3.3` con `@tailwindcss/vite`.
- Base de datos local: MySQL.
- Sesiones y cola: base de datos.
- Cache: archivos locales.
- Almacenamiento predeterminado: disco local de Laravel, ubicado fuera del document root.
- Permisos: Spatie Laravel Permission `6.25.0`.

La configuración frontend de producción se compila antes de subir el proyecto. El servidor no necesita `node_modules` si se carga `public/build` ya generado.

## Requisitos de cPanel

Confirmar en cPanel antes de subir:

- PHP 8.3 seleccionado para el dominio y para CLI.
- Composer disponible, o instalación local de `vendor/` mediante `composer install --no-dev --optimize-autoloader`.
- Extensiones PHP: `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath` y `zip`.
- Apache con `mod_rewrite` habilitado.
- MySQL disponible y una base exclusiva para producción.
- SSL activo para `opsbiomed.alfreval.com`.
- Cron jobs habilitados.
- Permisos de escritura para `storage/` y `bootstrap/cache/`.

En cPanel, confirmar la ruta real del PHP 8.3. En servidores EasyApache suele ser similar a:

```text
/opt/cpanel/ea-php83/root/usr/bin/php
```

Usar la ruta mostrada por el proveedor, no asumirla.

## Estructura y document root

Crear o usar el directorio privado del proyecto:

```text
/home/jesusvalera/ops-biomed
```

Configurar el document root del dominio exactamente en:

```text
/home/jesusvalera/ops-biomed/public
```

El proyecto completo debe quedar fuera del document root excepto el contenido de `public/`. No apuntar el dominio a `/home/jesusvalera/ops-biomed`, porque expondría archivos de configuración y código no público.

## Preparación local

Ejecutar desde la raíz del proyecto:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Si Composer o npm no están en el PATH de Windows, usar los binarios de Laragon. El resultado esperado es:

- `vendor/` generado para producción.
- `public/build/manifest.json` y assets compilados.
- Sin necesidad de subir `node_modules/`.

Subir, como mínimo:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `vendor/`
- `artisan`
- `composer.json`
- `composer.lock`
- `.env` de producción creado en el servidor

No subir:

- `.env` local.
- `node_modules/`.
- `tests/` si la política del servidor no los requiere.
- `.git/`.
- respaldos SQL dentro de `public/`.

## Variables de producción

Usar `.env.production.example` como plantilla. Crear el `.env` real en `/home/jesusvalera/ops-biomed/.env` y reemplazar todos los valores sensibles.

Valores mínimos:

```dotenv
APP_NAME="OPS BIOMED MR8"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://opsbiomed.alfreval.com
APP_TIMEZONE=America/Lima

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<base_de_datos_cpanel>
DB_USERNAME=<usuario_mysql_cpanel>
DB_PASSWORD=<contraseña_mysql_cpanel>

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.opsbiomed.alfreval.com
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=<servidor_smtp>
MAIL_PORT=<puerto_smtp>
MAIL_USERNAME=<usuario_smtp>
MAIL_PASSWORD=<contraseña_smtp>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<correo_remitente>
MAIL_FROM_NAME="OPS BIOMED MR8"
```

Conservar el `APP_KEY` existente si se migra una instalación ya usada. Si es una instalación nueva, generarlo una sola vez con `php artisan key:generate --force` antes de activar usuarios. Nunca versionar ni enviar el valor de `APP_KEY` por correo o chat.

## Base de datos, migraciones y seeders

1. Crear una base MySQL independiente en cPanel.
2. Crear un usuario MySQL con contraseña fuerte y privilegios solo sobre esa base.
3. Importar un respaldo aprobado únicamente si se migra información existente.
4. Respaldar la base remota antes de migrar.
5. Ejecutar desde `/home/jesusvalera/ops-biomed`:

```bash
php artisan migrate:status
php artisan migrate --force
php artisan db:seed --class=RoleAndPermissionSeeder --force
```

En producción no ejecutar `migrate:fresh`, `db:wipe` ni `DatabaseSeeder` sin una decisión explícita de operación. Tampoco ejecutar `PilotDemoSeeder` en la base real: ese seeder es únicamente para el piloto controlado.

Después verificar:

```bash
php artisan migrate:status
```

## Storage y archivos privados

Ejecutar:

```bash
php artisan storage:link
chmod -R ug+rwX storage bootstrap/cache
```

Las evidencias, documentos, reportes de importación y archivos privados deben permanecer fuera de `public/`. Validar que no sea posible descargar `.env`, logs, archivos de `storage/app/private` ni el contenido de `vendor/` mediante una URL directa.

Si el hosting no permite enlaces simbólicos, no exponer manualmente `storage/app/private`; solicitar al proveedor una alternativa segura para `storage:link` y mantener los documentos privados detrás de las rutas autenticadas de Laravel.

## Cachés y optimización

Después de configurar `.env` y migrar:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

Si se modifica `.env`, repetir `php artisan optimize:clear` y volver a generar las cachés. No dejar `APP_DEBUG=true` en una caché de configuración de producción.

## Scheduler y cola de trabajos

La aplicación tiene programados:

- `ops:alerts:evaluate` cada hora.
- `queue:prune-batches --hours=48` diariamente.

Agregar en cPanel una tarea cron cada minuto. Sustituir la ruta de PHP por la confirmada por el proveedor:

```cron
* * * * * cd /home/jesusvalera/ops-biomed && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

La configuración actual usa la cola de base de datos. Confirmar que las tablas `jobs`, `job_batches` y `failed_jobs` existan y agregar otra tarea cron para procesar trabajos:

```cron
* * * * * cd /home/jesusvalera/ops-biomed && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work database --stop-when-empty --tries=3 --timeout=90 >> storage/logs/queue.log 2>&1
```

Verificar con:

```bash
php artisan schedule:list
php artisan queue:failed
php artisan ops:alerts:evaluate
```

No activar Redis ni Supervisor si el plan cPanel no los ofrece. La cola database es suficiente para el alcance actual, siempre que el cron funcione.

## Seguridad antes de activar el dominio

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- HTTPS válido y redirección HTTP a HTTPS en cPanel.
- Cookies de sesión seguras.
- CSRF activo en formularios.
- Rate limiting activo en login, API e importador.
- `storage/app/private` fuera del document root.
- Logs sin datos sensibles de pacientes.
- Usuarios nominales y roles mínimos necesarios.
- Cambiar contraseñas temporales del piloto antes de entregar accesos.
- Confirmar que Comercial no vea cantidades financieras internas no autorizadas.
- Configurar backups automáticos y probar una restauración.
- Mantener un respaldo de `.env`, base de datos y archivos privados antes de cada cambio.

## Verificación funcional post-despliegue

Con HTTPS activo, comprobar como mínimo:

```text
/login
/dashboard/ops
/cases
/schedule
/inventory
/inventory/coverage
/inventory/forecast
/alerts
/failures
/returns
/documents
/billing
/reports
/manual
/trace
```

Comprobar además:

- Inicio de sesión, cierre de sesión y recuperación de contraseña.
- Dashboard sin error 500.
- Creación y control de un caso de prueba aprobado.
- Reserva y validación de stock.
- Descarga autorizada de documentos y CSV.
- Ejecución de una alerta SLA.
- Tareas cron y cola procesando sin acumular errores.
- Prohibición de acceso directo a archivos privados.

## Validación local registrada

Antes de este runbook se verificó localmente:

- Laravel `12.69.2` y PHP `8.3.33`.
- Extensiones requeridas, incluyendo `pdo_mysql`, `mbstring`, `fileinfo`, `bcmath` y `zip`.
- Migraciones locales aplicadas.
- Scheduler con `ops:alerts:evaluate` cada hora.
- Vite/Tailwind compilados previamente con `npm run build`.
- Suite de pruebas previa: `244 passed`.
- Caché de vistas generada correctamente.

La ruta `public/storage` local todavía debe crearse con `php artisan storage:link` cuando se verifique el flujo de archivos. Esto no bloquea la compilación, pero sí debe resolverse antes de probar evidencias públicas autorizadas.

## Rollback

Antes de una migración o actualización:

1. Activar mantenimiento solo durante la ventana aprobada.
2. Respaldar la base de datos.
3. Respaldar `.env`.
4. Respaldar `storage/app/private` y `public/build`.
5. Registrar versión desplegada y hora.

Si falla la validación:

```bash
php artisan down
```

Restaurar la versión anterior del código, `.env`, base de datos y archivos privados según el respaldo aprobado. No ejecutar comandos destructivos como `migrate:fresh` o `db:wipe` durante una recuperación.

## Criterio de despliegue completado

El despliegue solo se considerará completado cuando:

1. `https://opsbiomed.alfreval.com` sirva directamente `public/index.php`.
2. El certificado HTTPS sea válido.
3. Login y dashboard carguen sin error 500.
4. Migraciones, permisos, scheduler y cola estén verificados.
5. Las pruebas funcionales mínimas y el acceso a archivos privados sean correctos.
6. Exista respaldo y rollback probado o documentado.

Mientras no exista acceso al cPanel y al dominio, el estado correcto es **preparación lista, despliegue remoto pendiente**.
