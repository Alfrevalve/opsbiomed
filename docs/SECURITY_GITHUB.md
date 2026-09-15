# Seguridad del repositorio y despliegue

## Repositorio

- Usar un repositorio privado llamado `ops-biomed`.
- Proteger `main` y exigir Pull Request.
- Exigir CI verde antes de merge.
- Usar el Environment `production` con aprobacion manual.
- Mantener secretos solo en GitHub Secrets y en el `.env` del servidor.
- Activar Dependabot para Composer y npm.
- No subir `.env`, `APP_KEY`, contrasenas, tokens, llaves privadas, backups, logs, archivos de usuarios ni datos de pacientes.
- No subir `vendor/`, `node_modules/` ni artefactos locales de cache.

## Secretos de GitHub

El workflow de despliegue usa solamente:

```text
CPANEL_HOST
CPANEL_PORT
CPANEL_USER
CPANEL_SSH_KEY
CPANEL_APP_PATH
CPANEL_PHP_BIN
CPANEL_COMPOSER_BIN
```

La clave SSH debe ser dedicada al despliegue, tener permisos minimos y poder revocarse sin afectar otras cuentas. No imprimir variables secretas en logs de Actions.

## Aplicacion

- `APP_DEBUG=false` en produccion.
- HTTPS obligatorio.
- Cookies de sesion seguras y CSRF activo.
- Rate limiting en login, API e importador.
- Usuarios nominales, roles minimos y middleware `EnsureUserIsActive`.
- Policies y permisos verificadas para inventario, fallas, devoluciones, documentos, costos cero, facturacion y administracion.
- Storage privado para evidencias y documentos.
- Logs sin nombres de pacientes, historias clinicas ni credenciales.
- Backups cifrados y prueba de restauracion.

## Datos demo y seeders

`PilotDemoSeeder` es solo para ambientes de prueba. Su password se recibe desde `PILOT_DEMO_PASSWORD`, que debe vivir en un `.env` local ignorado. No ejecutar `PilotDemoSeeder` ni `DatabaseSeeder` en produccion.

El seeder de usuario inicial recibe `INITIAL_ADMIN_PASSWORD` desde el entorno y falla si no esta definido. Esto evita que una contrasena quede embebida en el codigo.

## Revisión antes de merge

```bash
composer validate --strict
composer audit --locked
php artisan test --compact
npm ci
npm run build
php artisan view:cache
```

Revisar tambien los cambios staged y verificar que no contengan `.env`, logs, backups, pacientes reales, archivos de importacion o secretos.
