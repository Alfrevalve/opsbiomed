# Hotfix Breeze tests

Este ajuste corrige los fallos de `php artisan test` despues de instalar Breeze.

## Problemas detectados

- `Route [dashboard] not defined`.
- `/profile` devolvia 404.
- Algunas rutas de auth devolvian 403 por middleware de usuario activo.
- `/` devolvia 302 y el test base esperaba 200.

## Archivos corregidos

- `routes/web.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- `resources/views/welcome.blade.php`

## Comandos despues de copiar el parche

    php artisan optimize:clear
    php artisan test

## Resultado esperado

Breeze debe poder autenticar, cerrar sesion, verificar correo, confirmar password, actualizar perfil y pasar las pruebas base.
