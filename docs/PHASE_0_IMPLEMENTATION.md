# Fase 0 - Implementacion

## Objetivo

Dejar la base Laravel lista para construir el MVP operativo.

## Entregables

1. Laravel instalado.
2. Autenticacion activa.
3. Spatie Permission instalado y migrado.
4. Roles y permisos cargados.
5. Migraciones de dominio ejecutadas.
6. Seeders MR8 cargados.
7. Auditoria base disponible.
8. Dashboard inicial protegido por login.

## Comandos

    composer install
    cp .env.example .env
    touch database/database.sqlite
    php artisan key:generate
    composer require laravel/breeze --dev
    php artisan breeze:install blade
    php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
    php artisan migrate
    php artisan db:seed --class=RoleAndPermissionSeeder
    php artisan test

## Criterio de cierre

- Un usuario autorizado entra al dashboard.
- Los roles existen.
- Los almacenes base existen.
- Los tipos de cirugia existen.
- Las reglas de kit existen.
- Un caso puede registrarse con datos minimos.
- Una reserva no se permite si falta stock critico.
