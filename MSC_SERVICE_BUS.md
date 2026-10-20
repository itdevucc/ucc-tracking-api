# Recepción de mensajes de MSC

Esta primera fase recibe y guarda mensajes; no modifica eventos del tracking.
No cambia la frecuencia de consultas al API. Los mensajes permanecen PENDING
hasta implementar el procesamiento incremental NEW/UPDATE/DELETE.

## Configuración

En `.env`, configurar `MSC_SERVICE_BUS_CONNECTION_STRING` y
`MSC_SERVICE_BUS_QUEUE`. La conexión debe contener Endpoint,
SharedAccessKeyName y SharedAccessKey. Si incluye EntityPath, debe coincidir
con la cola. Se requiere permiso Listen. No publicar la cadena de conexión.

Ejecutar `php artisan config:clear` después de modificar las variables.

## Preparación y prueba

Crear únicamente la tabla de esta integración:

```bash
php artisan migrate --path=database/migrations/2026_10_20_000001_create_msc_service_bus_messages_table.php
php artisan msc:consume --once
```

`--once` espera hasta 20 segundos por un mensaje. Si lo recibe, lo guarda y
lo confirma: esto sí elimina ese mensaje de Azure, conservando su copia local.
No es una inspección pasiva. Una cola vacía también es un resultado válido.

Para escuchar continuamente:

```bash
php artisan msc:consume
```

Mantener la terminal abierta durante el desarrollo; detener con Ctrl+C.
En producción se requiere un servicio o administrador de procesos que mantenga
el comando activo. No dejar habilitada la recepción continua como integración
final hasta contar con el procesador y monitoreo de mensajes PENDING.

## Responsabilidades del código

- `MscServiceBusClient`: interpreta la conexión, firma un token SAS temporal,
  recibe mediante POST /messages/head (Peek-Lock) y confirma con DELETE al
  Location del bloqueo. HTTPS REST permite usar el cliente HTTP de Laravel.
- `MscServiceBusInbox`: guarda cuerpo original, headers (incluidas las
  propiedades de MSC) y propiedades del broker en una transacción. Identifica
  reentregas por origen y SequenceNumber para no insertar otra copia.
- `ConsumeMscServiceBus`: coordina recibir → guardar → confirmar. No confirma
  si falla el almacenamiento. En modo continuo reintenta después de 5 segundos.
- `msc_service_bus_messages`: conserva datos PENDING. acknowledged_at indica
  que la confirmación fue exitosa; no significa que se aplicó al tracking.

Si Azure confirma pero se pierde la respuesta, la copia local sigue existiendo.
Si expira el bloqueo antes de confirmar, Azure puede volver a entregar el
mensaje y la clave única evita duplicar la recepción. Los duplicados lógicos
de eventos distintos se deberán resolver en el futuro procesador DCSA.

Los mensajes inválidos también se guardan para posterior diagnóstico. El cuerpo
no se interpreta todavía. Las excepciones de base de datos y los cuerpos de error
remotos no se imprimen para evitar exponer payloads o credenciales.

## Verificación automatizada

```bash
php artisan test --compact --filter=MscServiceBusTest
```

Las pruebas usan HTTP simulado y SQLite en memoria; no consumen mensajes de MSC.

Referencias:
- https://learn.microsoft.com/en-us/rest/api/servicebus/peek-lock-message-non-destructive-read
- https://learn.microsoft.com/en-us/rest/api/servicebus/delete-message
- https://learn.microsoft.com/en-us/azure/service-bus-messaging/service-bus-sas
