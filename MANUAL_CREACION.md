# Manual de creación — UCC Tracking App

## 1. Requisitos utilizados

- Windows con Laragon.
- PHP 8.3.30 o posterior.
- Composer 2.10 o posterior.
- Node.js y npm 11 o posterior.

## 2. Creación del proyecto

```powershell
cd C:\laragon\www
composer create-project laravel/laravel:^13.0 ucc-tracking-app
cd ucc-tracking-app
```

El proyecto quedó instalado con Laravel 13, una clave `APP_KEY` propia y SQLite como base de datos local inicial.

## 3. Login y dashboard

Se instaló Laravel Breeze con Blade:

```powershell
composer require laravel/breeze --dev
php artisan breeze:install blade --no-interaction
```

Breeze incorpora registro, login, recuperación de contraseña, confirmación de contraseña, cierre de sesión, protección CSRF y regeneración segura de la sesión. El login limita cada combinación de correo e IP a cinco intentos antes de bloquearla temporalmente.

El modelo `User` implementa verificación de correo. Por ello `/dashboard` exige los middleware `auth` y `verified`.

En desarrollo, los correos se guardan en `storage/logs/laravel.log` porque `MAIL_MAILER=log`. Para producción se debe configurar un proveedor SMTP real.

## 4. Sanctum y API

La API fue instalada mediante:

```powershell
php artisan install:api --no-interaction
```

La aplicación incluye Laravel Sanctum 4, la tabla `personal_access_tokens`, el trait `HasApiTokens` y tokens con vencimiento predeterminado de 60 minutos. El prefijo `ucc_tracking_` ayuda a identificar una filtración mediante herramientas de detección de secretos.

### Crear un token

El usuario debe tener el correo verificado.

```bash
curl -X POST http://ucc-tracking-app.test/api/auth/tokens \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario@ejemplo.com","password":"contraseña","device_name":"Postman","abilities":["tracking:read"]}'
```

Permisos admitidos: `tracking:read` y `tracking:write`. Si se omiten, el token recibe ambos. La respuesta muestra el token en texto plano una sola vez; nunca debe guardarse en el repositorio ni enviarse por correo o chat.

### Consumir una ruta protegida

```bash
curl http://ucc-tracking-app.test/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN_AQUI"
```

### Gestionar tokens

Todas estas rutas requieren `Authorization: Bearer TOKEN_AQUI`:

| Método | Ruta | Acción |
| --- | --- | --- |
| `GET` | `/api/auth/tokens` | Lista metadatos; nunca revela los tokens. |
| `DELETE` | `/api/auth/token` | Revoca el token usado en la solicitud. |
| `DELETE` | `/api/auth/tokens` | Revoca todos los tokens del usuario. |

La creación de tokens está limitada a cinco solicitudes por minuto por correo e IP.

## 5. Arranque local

Laragon normalmente publica automáticamente la aplicación en:

```text
http://ucc-tracking-app.test
```

Si el dominio no aparece, pulsa **Reload** en Laragon. Como alternativa:

```powershell
php artisan serve
npm run dev
```

Para una compilación estática del frontend:

```powershell
npm run build
```

## 6. Base de datos

Por defecto se utiliza `database/database.sqlite`. Para MySQL, crear una base de datos y modificar `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ucc_tracking_app
DB_USERNAME=root
DB_PASSWORD=
```

Después:

```powershell
php artisan migrate
```

### Conexión de lectura a Sailor

La aplicación usa una conexión independiente llamada `sailor_source`. En el
entorno de desarrollo y pruebas debe apuntar a `sailor_qa`; la base principal de
la aplicación continúa siendo `sailor_tracking`.

```dotenv
SAILOR_SOURCE_DB_CONNECTION=sailor_source
SAILOR_SOURCE_DB_DRIVER=mysql
SAILOR_SOURCE_DB_HOST=servidor-qa
SAILOR_SOURCE_DB_PORT=3306
SAILOR_SOURCE_DB_DATABASE=sailor_qa
SAILOR_SOURCE_DB_USERNAME=usuario_solo_lectura
SAILOR_SOURCE_DB_PASSWORD=secreto
SAILOR_SOURCE_ALLOWED_DATABASES=sailor_qa
SAILOR_SOURCE_STATES_TABLE=booking_itinerary_states
SAILOR_SOURCE_STATE_CODES=CONFIRMED,EMBARKED
SAILOR_SOURCE_CARRIER_CODES=HLCU,MSCU
```

Esta conexión solo se utiliza para consultar `booking_itineraries` y
`sys_navieras`. El código rechaza la conexión si apunta a `sailor_tracking` o a
una base que no figure en `SAILOR_SOURCE_ALLOWED_DATABASES`. Adicionalmente,
cada sesión se inicia en modo de transacción de solo lectura.

El administrador de base de datos debe crear un usuario exclusivo con permiso
`SELECT` únicamente sobre esas dos tablas. En QA y producción deben utilizarse
credenciales distintas. Para producción se cambian las variables
`SAILOR_SOURCE_DB_*` y se autoriza únicamente el nombre real de la base de
producción; no se modifica el código.

Después de cambiar variables de entorno:

```powershell
php artisan config:clear
php artisan tracking:source-check
php artisan tracking:source-bookings --limit=20
```

## 7. Verificación y mantenimiento

```powershell
php artisan test
composer audit
npm audit
php artisan route:list
```

## 8. Ajustes obligatorios antes de producción

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tracking.ejemplo.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SANCTUM_STATEFUL_DOMAINS=tracking.ejemplo.com
```

Además, se debe usar HTTPS, configurar SMTP, ejecutar `php artisan config:cache`, mantener `APP_KEY` en secreto, restringir CORS a los dominios reales, programar copias de seguridad y conservar Laravel y sus dependencias actualizados.
