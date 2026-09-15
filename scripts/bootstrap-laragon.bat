@echo off
setlocal

where php >nul 2>nul
if errorlevel 1 (
  echo PHP no esta disponible. Abre la Terminal desde Laragon.
  exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
  echo Composer no esta disponible. Instalalo o activalo desde Laragon.
  exit /b 1
)

if not exist .env copy .env.laragon.example .env

composer install
php artisan key:generate
php artisan breeze:install blade
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force
php artisan migrate
php artisan db:seed --class=RoleAndPermissionSeeder
echo Define INITIAL_ADMIN_PASSWORD en .env antes de ejecutar InitialUserSeeder.

where npm >nul 2>nul
if not errorlevel 1 (
  npm install
  npm run build
)

php artisan test
endlocal
