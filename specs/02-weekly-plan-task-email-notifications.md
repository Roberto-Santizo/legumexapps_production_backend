# SPEC 02 — Notificación por correo de cambios en WeeklyPlanTask

> **Estado:** Aprobado
> **Depende de:** SPEC 01
> **Fecha:** 2026-08-03
> **Objetivo:** Enviar un correo a una lista fija de destinatarios cada vez que se crea o se modifica una `WeeklyPlanTask`, usando el sistema de correo nativo de Laravel para poder cambiar de proveedor sin tocar código.

---

## Por qué existe esta spec

La SPEC 01 dejó el rastro de quién cambió qué en `weekly_plan_task_logs`, pero ese rastro es pasivo: hay que ir a mirarlo. Cuando alguien mueve la fecha de operación de una tarea o le cambia las cajas, planificación se entera tarde o no se entera.

El envío se engancha al mismo `WeeklyPlanTaskObserver` que ya escribe el log, por la misma razón que en la SPEC 01: no queda ninguna escritura que se salte los eventos de modelo, así que ningún método futuro puede olvidarse de notificar.

**Sobre la adaptabilidad de proveedor:** no se construye ninguna abstracción propia. Laravel ya es la abstracción — `config/mail.php` soporta `smtp`, `ses`, `postmark`, `resend`, `sendmail`, `log`, `failover` y `roundrobin`, y el código solo habla con la fachada `Mail`. Cambiar de Resend a SES en producción es cambiar `MAIL_MAILER` en el `.env`, sin tocar ni una línea de PHP. Resend es la elección de desarrollo, no una dependencia del diseño.

---

## Alcance

**Dentro:**

- Instalación de `resend/resend-php`, requisito del transport `resend` que Laravel ya declara en `config/mail.php`.
- Lista de destinatarios configurable por variable de entorno, leída desde `config/mail.php`.
- `app/Helpers/MailHandler.php`: único punto de envío. Resuelve destinatarios, sale en silencio si no hay ninguno, y captura cualquier excepción de envío escribiéndola en el log.
- Correo **individual** en los eventos `created` y `updated` del observer, un correo por tarea, con todos los campos cambiados en el mismo mensaje.
- Correo **agrupado** para `assignOperationDate`: un solo correo con todas las tareas afectadas, enviado por el servicio después del commit, con el observer silenciado durante la operación.
- Dos Mailables con sus vistas Blade.
- Nuevas variables documentadas en `.env.example`.

**Fuera de alcance (para specs futuras):**

- Envío encolado (`ShouldQueue`). Decisión explícita: el envío es síncrono.
- Notificación del evento `deleted`. Sigue fuera, igual que en la SPEC 01.
- Notificar cambios de campos distintos a `WeeklyPlanTask::LOGGED_FIELDS`.
- Suscriptores en base de datos, preferencias por usuario o desactivación individual.
- Notificaciones por otros canales (Slack, base de datos, push).
- Contexto de plan semanal, línea y SKU dentro del correo. El correo identifica la tarea por su `id` y muestra los valores tal cual quedan en el log.
- Enlace al frontend dentro del correo.
- Reintentos, backoff o cola de correos fallidos.
- Tests automatizados. El repositorio sigue sin carpeta `tests/` y esta spec no la crea.
- Notificación agrupada para `splitWeeklyPlanTask`: manda un correo individual `created` por cada tarea resultante, decisión explícita.

---

## Modelo de datos

Esta spec **no introduce tablas ni columnas nuevas**. Toda la configuración vive en `.env` y `config/`.

### Configuración

`.env.example` (valores de ejemplo, no reales):

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS="no-reply@legumex.com"
MAIL_FROM_NAME="${APP_NAME}"
WEEKLY_PLAN_TASK_NOTIFY_EMAILS=
```

`config/mail.php`, nueva clave de primer nivel al lado de `from`:

```php
'weekly_plan_task_recipients' => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('WEEKLY_PLAN_TASK_NOTIFY_EMAILS', ''))
))),
```

`config/services.php` ya tiene `resend.key` apuntando a `RESEND_API_KEY`. No se toca.

### Contrato de los correos

| Correo                                  | Se dispara en                                        | Destinatarios                              | Asunto                                                     |
| --------------------------------------- | ---------------------------------------------------- | ------------------------------------------ | ---------------------------------------------------------- |
| `WeeklyPlanTaskChanged`                 | `created` y `updated` del observer                   | `config('mail.weekly_plan_task_recipients')` | `Nueva tarea de plan semanal #{id}` / `Cambios en la tarea de plan semanal #{id}` |
| `WeeklyPlanTasksOperationDateAssigned`  | `assignOperationDate`, después del commit            | `config('mail.weekly_plan_task_recipients')` | `Fecha de operación asignada a {N} tareas`                 |

### Contenido de `WeeklyPlanTaskChanged`

Datos que se pasan a la vista:

```php
[
    'event' => 'created' | 'updated',
    'taskId' => int,
    'userName' => string,          // auth()->user()->name
    'changedAt' => Carbon,         // now()
    'changes' => [                 // array shape: {field, label, old, new}
        ['field' => 'boxes', 'label' => 'Cajas', 'old' => '100', 'new' => '120'],
    ],
]
```

- En `updated`, `changes` contiene **una entrada por campo rastreado que cambió**, con los mismos valores que la SPEC 01 escribe en `weekly_plan_task_logs`.
- En `created`, `changes` contiene los **valores iniciales** de los tres campos rastreados: `old` va vacío y `new` lleva el valor con el que nació la tarea.
- Etiquetas en español: `boxes` → `Cajas`, `operation_date` → `Fecha de operación`, `line_sku_id` → `Línea/SKU`.
- Un `updated` que no toca ningún campo rastreado **no envía correo**, exactamente igual que no escribe log.

### Contenido de `WeeklyPlanTasksOperationDateAssigned`

```php
[
    'operationDate' => string,     // la fecha asignada a todo el lote
    'userName' => string,
    'changedAt' => Carbon,
    'tasks' => [                   // array shape: {id, oldOperationDate}
        ['id' => 12, 'oldOperationDate' => '2026-08-01 00:00:00'],
        ['id' => 13, 'oldOperationDate' => null],
    ],
]
```

- `oldOperationDate` se captura **antes** de guardar cada tarea, dentro de la transacción.
- Si ninguna tarea cambió realmente de fecha (todas ya tenían esa `operation_date`), la lista queda vacía y **no se envía correo**.

### Silenciar el observer durante el lote

`WeeklyPlanTaskObserver` expone un interruptor estático para que `assignOperationDate` mande su propio correo agrupado sin que el observer dispare N correos individuales:

```php
public static bool $sendsMail = true;

/** Run a callback with the individual change email disabled. */
public static function withoutMail(callable $callback): mixed
{
    self::$sendsMail = false;

    try {
        return $callback();
    } finally {
        self::$sendsMail = true;
    }
}
```

El interruptor **solo apaga el correo**. La escritura en `weekly_plan_task_logs` y la validación de usuario autenticado de la SPEC 01 siguen funcionando igual dentro del lote.

### `MailHandler`

`app/Helpers/MailHandler.php`, misma forma que `ResponseHandler` y `ErrorHandler` (clase con métodos estáticos, sin estado):

```php
public static function notifyWeeklyPlanTaskRecipients(Mailable $mailable): void
```

Responsabilidades, en orden:

1. Lee `config('mail.weekly_plan_task_recipients')`. Si está vacío, **retorna sin hacer nada y sin escribir log**.
2. `Mail::to($recipients)->send($mailable)`.
3. Envuelve el envío en `try/catch (\Throwable $e)` y escribe `Log::error()` con el mensaje de la excepción y la clase del Mailable. **Nunca relanza.**

Es el único sitio del proyecto que llama a `Mail::`. El observer y el servicio no conocen ni los destinatarios ni el manejo de errores.

---

## Plan de implementación

1. **Dependencia.** `composer require resend/resend-php`. Verificación: `composer show resend/resend-php` responde y `php artisan config:show mail.default` no rompe.
2. **Configuración.** Añadir `weekly_plan_task_recipients` a `config/mail.php` y las variables nuevas a `.env.example`. En el `.env` local, `MAIL_MAILER=log` para desarrollar sin gastar envíos. Verificación: `php artisan config:show mail.weekly_plan_task_recipients` devuelve el array parseado desde la variable.
3. **`MailHandler`.** `php artisan make:class Helpers/MailHandler --no-interaction` con el método de arriba. Verificación: con la lista vacía, invocarlo no lanza excepción.
4. **Mailable individual.** `php artisan make:mail WeeklyPlanTaskChanged --markdown=emails.weekly-plan-task-changed --no-interaction`. Asunto dependiente de `event`, tabla de cambios en la vista.
5. **Mailable agrupado.** `php artisan make:mail WeeklyPlanTasksOperationDateAssigned --markdown=emails.weekly-plan-tasks-operation-date-assigned --no-interaction`. Asunto con el conteo de tareas, tabla de tareas afectadas.
6. **Observer.** En `WeeklyPlanTaskObserver`: añadir `$sendsMail` y `withoutMail()`; en `created`, tras escribir el log, construir el `WeeklyPlanTaskChanged` con los valores iniciales y pasarlo a `MailHandler`; en `updated`, acumular los campos cambiados **en el mismo bucle que ya escribe los logs** y enviar **un solo** correo al final si hay al menos un cambio. Ambos envíos condicionados a `self::$sendsMail`. Verificación con `MAIL_MAILER=log`: `POST /weekly-plan-tasks` deja el cuerpo del correo en `storage/logs/laravel.log`.
7. **`assignOperationDate`.** Envolver la transacción existente en `WeeklyPlanTaskObserver::withoutMail(...)`, capturar dentro del bucle el `operation_date` previo de cada tarea que realmente cambia, y **después** de que la transacción retorne enviar el correo agrupado vía `MailHandler`. La firma del método y su retorno (`true`) no cambian. Verificación: asignar fecha a 3 tareas deja **un** correo en el log, no tres.
8. **Formato.** Ejecutar `vendor/bin/pint --dirty --format agent`.

`splitWeeklyPlanTask`, `createWeeklyPlanTask` y `updateWeeklyPlanTaskById` quedan **sin tocar**: el observer los cubre solo.

---

## Criterios de aceptación

Verificación manual con `MAIL_MAILER=log` y `WEEKLY_PLAN_TASK_NOTIFY_EMAILS` con al menos un correo, revisando `storage/logs/laravel.log`.

- [ ] `POST /weekly-plan-tasks` con JWT válido genera **un** correo con asunto `Nueva tarea de plan semanal #{id}` y los valores iniciales de `boxes`, `operation_date` y `line_sku_id`.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando `boxes` de 100 a 120 genera **un** correo con asunto `Cambios en la tarea de plan semanal #{id}` que muestra `Cajas: 100 → 120`.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando `boxes` y `operation_date` a la vez genera **un solo** correo con **dos** filas de cambio.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando solo `produced_boxes` **no** genera ningún correo.
- [ ] `PUT /weekly-plan-tasks/{id}` enviando `boxes` con el mismo valor que ya tenía **no** genera ningún correo.
- [ ] `POST /weekly-plan-tasks/assignOperationDate` con 3 ids genera **exactamente un** correo con asunto `Fecha de operación asignada a 3 tareas` y una fila por tarea con su fecha anterior individual.
- [ ] Ese mismo `assignOperationDate` sigue insertando las **3 filas** en `weekly_plan_task_logs` (la SPEC 01 no se degrada).
- [ ] `POST /weekly-plan-tasks/splitTask` que divide una tarea en 2 genera **2** correos individuales de alta.
- [ ] Con `WEEKLY_PLAN_TASK_NOTIFY_EMAILS` vacía, todas las operaciones anteriores funcionan y **no** se genera correo ni error.
- [ ] Con `MAIL_MAILER=resend` y `RESEND_API_KEY` inválida, `PUT /weekly-plan-tasks/{id}` **devuelve 200**, guarda el cambio, escribe el log y deja un `ERROR` en `storage/logs/laravel.log`.
- [ ] Con `MAIL_MAILER=resend` y credenciales válidas, el correo llega a la bandeja de los destinatarios configurados.
- [ ] Cambiar `MAIL_MAILER` de `resend` a `log` y viceversa no requiere ningún cambio en PHP.
- [ ] `vendor/bin/pint --test` no reporta archivos con formato incorrecto.

---

## Decisiones

- **Sí:** `Mail` nativo de Laravel configurado por `MAIL_MAILER`. Es la abstracción de proveedor que pide el objetivo; Resend, SES, Postmark, SMTP y `log` se intercambian por `.env`.
- **No:** interface + service + provider propios para el correo (`MailerServiceInterface`). Sería una capa encima de una abstracción que ya existe, con un solo implementador real, y obligaría a reimplementar lo que `MailManager` ya resuelve.
- **No:** mailer `failover`. Añade una segunda configuración de proveedor para un caso que hoy no existe; el `try/catch` de `MailHandler` ya evita que un fallo tumbe la operación. Si hace falta, es cambio de config, no de código.
- **Sí:** disparar desde el observer. Misma justificación que la SPEC 01: ningún método futuro puede olvidarse de notificar.
- **Sí:** notificar `created` y `updated`. El alta de una tarea también mueve la planificación.
- **No:** notificar `deleted`. Sigue fuera por la misma razón que en la SPEC 01.
- **Sí:** envío síncrono. Decisión explícita del usuario. La cola existe (`QUEUE_CONNECTION=database`) pero exige un worker vivo en producción; el correo síncrono no depende de infraestructura extra.
- **Sí:** tragarse el error de envío y escribirlo en el log. El correo es un aviso, no parte de la operación; un proveedor caído no puede impedir que se guarde un cambio de planificación.
- **Sí:** un solo correo por operación de usuario, no uno por campo. El log de la SPEC 01 sí es una fila por campo porque se consulta con `where`; el correo se lee.
- **Sí:** lista fija de destinatarios en `.env`. Cambiar quién recibe no requiere despliegue ni migración.
- **No:** destinatarios por rol de usuario o tabla de suscriptores. La columna `email` de `users` no está garantizada como poblada y una tabla de suscripciones es esquema nuevo para un caso que hoy se resuelve con una variable.
- **Sí:** salir en silencio si no hay destinatarios. Es el interruptor de apagado: vaciar la variable desactiva la notificación en local, seeders y comandos sin tocar código.
- **Sí:** correo agrupado en `assignOperationDate`, enviado desde el servicio con el observer silenciado. Asignar fecha a 20 tareas es **una** decisión del usuario, no 20; y con envío síncrono serían 20 llamadas HTTP en una sola request.
- **No:** colector genérico de lotes en el observer. Más maquinaria para un solo caso; cuando aparezca el segundo, se generaliza en otra spec.
- **Sí:** enviar el correo agrupado **después** del commit. Un rollback no puede dejar avisos de cambios que no se guardaron.
- **Sí:** correos individuales en `splitWeeklyPlanTask`. Decisión explícita del usuario: cada tarea resultante es un alta real y se notifica como tal.
- **Sí:** `MailHandler` en `app/Helpers/`. Sigue la convención de `ResponseHandler` y `ErrorHandler`, y concentra destinatarios y manejo de errores en un solo sitio.
- **Sí:** solo `LOGGED_FIELDS` disparan correo. Se mantiene la coherencia con la SPEC 01: lo que no se registra, no se notifica.
- **No:** incluir plan semanal, línea y SKU en el correo. Obligaría a cargar relaciones dentro del observer en cada guardado. Decisión explícita del usuario.
- **No:** tests Pest. Decisión explícita del usuario; el repositorio sigue sin carpeta `tests/`.

---

## Riesgos

| Riesgo                                                                                              | Mitigación                                                                                                                        |
| --------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| El envío síncrono añade la latencia de una llamada HTTP a cada `POST`/`PUT` de tarea               | Aceptado. Un solo correo por operación; `assignOperationDate` está agrupado. Si molesta, pasar a `ShouldQueue` es cambio aislado.  |
| Un fallo de envío es invisible para el usuario final                                                | Queda registrado como `ERROR` en `storage/logs/laravel.log` con la clase del Mailable. Sin él, no habría rastro alguno.            |
| Resend rechaza envíos desde un dominio no verificado                                                 | `MAIL_FROM_ADDRESS` debe usar un dominio verificado en Resend. En desarrollo se trabaja con `MAIL_MAILER=log`.                      |
| Un update masivo futuro (`whereIn(...)->update(...)`) se salta el observer y no notifica            | Misma regla de la SPEC 01: si el update toca `LOGGED_FIELDS`, se itera y se guarda modelo a modelo.                                |
| Alguien deja `$sendsMail = false` por una excepción a mitad de un lote                              | `withoutMail()` restaura el valor en un bloque `finally`, así que una excepción no deja el correo apagado para el resto del proceso.|
| Volumen de correo alto si se editan muchas tareas de una en una                                     | Aceptado. Es una consecuencia directa de notificar cada cambio; agrupar por request se evaluará si se vuelve ruidoso.               |
| `RESEND_API_KEY` en `.env` de producción                                                             | Nunca se commitea; `.env.example` la deja vacía. Rotarla no requiere despliegue.                                                    |

---

## Lo que **no** entra en esta spec

- Envío encolado y reintentos.
- Notificación de borrados.
- Destinatarios dinámicos (por rol, por tabla o por preferencia de usuario).
- Otros canales de notificación.
- Contexto de plan/línea/SKU y enlaces al frontend dentro del correo.
- Tests automatizados y creación de la carpeta `tests/`.

Cada uno de esos, si llega, va en su propia spec.
