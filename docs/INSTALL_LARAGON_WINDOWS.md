# Instalacion en Laragon Windows

Esta guia asume Laragon con Apache, MySQL y PHP 8.3 activos.

## 1. Crear Laravel limpio

Abre la Terminal de Laragon y ejecuta:

    cd C:\laragon\www
    composer create-project laravel/laravel ops-biomed
    cd ops-biomed
    composer require laravel/breeze --dev
    php artisan breeze:install blade

## 2. Copiar capa OPS BIOMED

Copia el contenido de esta carpeta sobre:

    C:\laragon\www\ops-biomed

Acepta reemplazar archivos cuando Windows lo solicite.

## 3. Crear base de datos

Desde Laragon:

1. Clic en **Base de Datos**.
2. Abrir HeidiSQL o cliente disponible.
3. Crear base:

    CREATE DATABASE ops_biomed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

## 4. Preparar entorno

Abrir Terminal de Laragon:

    cd C:\laragon\www\ops-biomed
    copy .env.laragon.example .env
    composer update
    php artisan key:generate
    php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
    php artisan migrate
    php artisan db:seed --class=RoleAndPermissionSeeder
    rem Define INITIAL_ADMIN_PASSWORD en .env y ejecuta InitialUserSeeder si necesitas el usuario inicial.
    npm install
    npm run build

## 5. Abrir sistema

En Laragon:

1. Clic derecho en Laragon.
2. Apache > Recargar.
3. Abrir:

    http://ops-biomed.test

Si el dominio automatico no resuelve, usar:

    http://localhost/ops-biomed/public

## 6. Verificacion rapida

    php artisan route:list
    php artisan migrate:status
    php artisan test

## Nota

Breeze instala el login visual. La capa OPS BIOMED deja roles, permisos, dominio, migraciones, auditoria y seeders MR8 preparados.
