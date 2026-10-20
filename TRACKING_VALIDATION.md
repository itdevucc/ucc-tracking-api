# Paridad entre consulta directa y tracking guardado

## Correcciones

- El reemplazo de eventos sigue siendo transaccional y por booking. Conserva el
  snapshot previo ante respuesta vacía o fallo. No acumula versiones anteriores.
- La identidad incluye BL: los 24 elementos del caso con dos BL conservan 24 filas.
- Cada contenedor recibe sus eventos y los eventos generales de su BL; los
  generales sin BL se mantienen compartidos cuando no hay una asociación conocida.
- Fechas convertidas a UTC antes de guardar. La migración cambia event_date_time
  a DATETIME sin DEFAULT/ON UPDATE CURRENT_TIMESTAMP. La vista directa puede
  conservar el offset del JSON: se prueba equivalencia de instantes y presentación.
- Ubicación y terminal usan una extracción compartida. Los eventos sin nombre no
  reemplazan un nombre explícito ya recibido. La consulta directa comparte los
  objetos de ubicación en memoria como el modelo persistido.
- Eventos sin ISO no borran la información de equipo previamente recibida.
- ETD/ETA y departure_event/arrival_event usan el mismo selector por origen/destino,
  dando prioridad a eventos reales en el puerto correspondiente.
- Cabecera muestra ambos BL y etiquetas de contenedor muestran su BL. Sailor App
  conserva respaldo a la propiedad anterior cuando el API todavía no tiene el campo.
- La fecha de recepción del raw se actualiza cuando vuelve un payload conocido,
  de modo que puede ordenarse por received_at y no por id para localizar el actual.
- Respuesta de tracking guardado lleva no-store para evitar reutilización en caché.

## Validación reproducible

La suite exige SQLite :memory: y APP_ENV=testing antes de ejecutar migraciones de
prueba. El fixture es anónimo, derivado de la estructura del caso MSC de 24 eventos.
No se usan credenciales, datos ni escrituras de producción. HTTP de navieras se simula.

```bash
php vendor/bin/phpunit tests/Feature/Tracking tests/Unit/CarrierEventPagesTest.php tests/Unit/TrackingPersistenceParityTest.php
node --test tests/js/tracking-presentation.test.mjs
npm run build
```

Casos: paridad de todos los campos visuales entre API directo y persistido, 24 filas
y 12 eventos por equipo, mismo ID/diferente BL, cambio de orden del payload, UTC,
fecha estable al actualizar, ISO, etiquetas/ubicaciones, descripción de carrier y
fallback, ciclo A-B-A, eliminación de obsoletos, vacío, rollback, cierre por destino,
job real con HTTP simulado frente a endpoint directo, rutas autenticadas, render
Blade, paginación MSC/Hapag, cursor repetido, página inválida, mapa por equipo y
presentación de fechas equivalentes. SQL de la migración MySQL se compila sin conexión.

## Aplicación en producción

Desplegar PHP y assets de UCC, el ajuste del modal Sailor App y la migración.
Con el usuario/PHP del servicio, ejecutar en el servidor:

```bash
cd /var/www/html/ucc-tracking-api
php artisan migrate --force
php artisan queue:restart
```

Confirmar que la siguiente sincronización del booking activo termine correctamente
y que API guardado y consulta directa muestren 12 eventos por equipo para el mismo
payload. Las pruebas no reemplazan esta comprobación del despliegue real.

La migración por sí sola no repara valores históricos. La próxima sincronización
reemplaza los eventos de activos desde MSC; los completados no se reactivan ni se
consultan automáticamente. Para ellos, cualquier reproceso histórico debe prepararse
y autorizarse por separado desde el raw correspondiente.

No se ejecutó la migración ni se escribieron eventos reales durante esta tarea.
