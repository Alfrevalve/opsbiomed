# Despliegue GitHub + cPanel

## Objetivo

Publicar OPS BIOMED MR8 desde un repositorio privado de GitHub hacia cPanel usando un build reproducible y un despliegue SSH manualmente autorizado.

Dominio:

```text
https://opsbiomed.alfreval.com
```

Document root obligatorio:

```text
/home/jesusvalera/ops-biomed/public
```

El proyecto completo vive en `/home/jesusvalera/ops-biomed`; el dominio nunca debe apuntar a ese directorio raiz.

## Preparar GitHub

1. Crear un repositorio **privado** llamado `ops-biomed`.
2. No crear un commit con `.env`, `vendor/`, `node_modules/`, logs, backups, evidencias privadas ni datos reales.
3. Proteger `main`:
   - Pull Request obligatorio.
   - CI obligatorio antes del merge.
   - Sin push directo salvo administradores.
4. Crear un Environment de GitHub llamado `production`.
5. Activar aprobacion manual para ese environment.
6. Activar Dependabot para Composer y npm cuando la politica del repositorio lo permita.

El workflow de calidad corre en cada pull request y en cada push a `main`. El workflow de produccion solo puede iniciarse con `workflow_dispatch`.

## Secretos del Environment production

Configurar exclusivamente en GitHub Secrets:

| Secreto | Valor |
| --- | --- |
| `CPANEL_HOST` | Host SSH del proveedor cPanel |
| `CPANEL_PORT` | Puerto SSH, normalmente `22` |
| `CPANEL_USER` | Usuario de cPanel |
| `CPANEL_SSH_KEY` | Clave privada SSH de despliegue |
| `CPANEL_APP_PATH` | `/home/jesusvalera/ops-biomed` |
| `CPANEL_PHP_BIN` | Ruta CLI PHP 8.3 del servidor |
| `CPANEL_COMPOSER_BIN` | Ruta de Composer o binario `composer` |

No guardar en GitHub:

- `APP_KEY`.
- Contraseña MySQL.
- Credenciales SMTP.
- Passwords de usuarios.
- Tokens de proveedores.

El `.env` real se crea y conserva unicamente en el servidor.

## Primer despliegue

### 1. Preparar cPanel

Crear una base MySQL independiente, un usuario con privilegios sobre esa base y configurar PHP 8.3 con `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath` y `zip`.

Configurar el dominio con document root `/home/jesusvalera/ops-biomed/public`, activar SSL y crear el directorio privado del proyecto.

### 2. Crear `.env` en el servidor

Usar `.env.production.example` como plantilla. Completar la base de datos, SMTP, `APP_KEY` y los valores propios del proveedor. Mantener:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://opsbiomed.alfreval.com
APP_TIMEZONE=America/Lima
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

No usar `INITIAL_ADMIN_PASSWORD` ni `PILOT_DEMO_PASSWORD` en produccion. No ejecutar seeders demo.

### 3. Crear el primer acceso administrativo

Si la base esta vacia, migrar primero y ejecutar solo los seeders aprobados:

```bash
cd /home/jesusvalera/ops-biomed
php artisan migrate --force
php artisan db:seed --class=RoleAndPermissionSeeder --force
```

Crear el usuario administrador mediante el procedimiento aprobado por el equipo, sin dejar la contrasena en el repositorio ni en los logs. No ejecutar `DatabaseSeeder` ni `PilotDemoSeeder` en produccion.

### 4. Ejecutar el workflow

En GitHub:

1. Abrir **Actions**.
2. Elegir **Deploy production**.
3. Seleccionar **Run workflow** sobre `main`.
4. Aprobar el environment `production`.

El workflow:

1. Reutiliza el workflow de CI.
2. Instala dependencias PHP de produccion.
3. Instala Node y compila Vite/Tailwind.
4. Genera un paquete sin `.env`, `.git`, `node_modules`, `tests`, logs ni archivos privados.
5. Sube el paquete por SSH.
6. Activa mantenimiento.
7. Respaldar `.env` y `storage/app/private`.
8. Actualiza el codigo sin comandos destructivos.
9. Ejecuta Composer, migraciones y `storage:link`.
10. Limpia y reconstruye caches.
11. Desactiva mantenimiento y verifica `/up`.

Si una etapa falla, el `trap` del workflow intenta ejecutar `php artisan up` para evitar que la aplicacion quede bloqueada permanentemente. El rollback de datos y codigo sigue siendo una decision operativa separada.

## Metodo alternativo: cPanel Git Version Control

Usar este metodo solo si el proveedor no permite SSH desde GitHub Actions:

1. Crear el repositorio privado y configurar la clave deploy de cPanel con minimo privilegio.
2. Clonar en `/home/jesusvalera/ops-biomed`, nunca en `public/`.
3. Ejecutar pull desde cPanel despues de aprobar el commit.
4. Ejecutar en el servidor:

```bash
cd /home/jesusvalera/ops-biomed
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

5. Configurar los cron jobs:

```cron
* * * * * cd /home/jesusvalera/ops-biomed && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/jesusvalera/ops-biomed && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

Sustituir la ruta PHP por la confirmada en ese servidor.

## Verificacion post-despliegue

```bash
cd /home/jesusvalera/ops-biomed
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
php artisan route:list --except-vendor
```

Desde fuera del servidor:

```bash
curl --fail --show-error --silent https://opsbiomed.alfreval.com/up
```

Comprobar manualmente `/login`, `/dashboard/ops`, `/cases`, `/inventory`, `/alerts`, `/documents`, `/billing`, `/reports`, `/manual` y `/trace`. Probar que Comercial no vea forecast interno ni edite pagos, y que los documentos privados no sean accesibles por URL directa.

## Variables de entorno y datos

El repositorio contiene solo plantillas sin credenciales. El archivo `.env` productivo, la base de datos, las evidencias y los respaldos viven fuera de GitHub. Para el procedimiento de rollback consultar [ROLLBACK_GITHUB_CPANEL.md](ROLLBACK_GITHUB_CPANEL.md).
