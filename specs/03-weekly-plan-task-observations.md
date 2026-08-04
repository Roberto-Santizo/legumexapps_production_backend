# SPEC 03 — Observaciones de WeeklyPlanTask

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-08-04
> **Objetivo:** Permitir que un usuario autenticado escriba observaciones de texto libre sobre una `WeeklyPlanTask`, que se acumulan como bitácora con autor y fecha, y que solo su autor puede editar o borrar.

---

## Por qué existe esta spec

La SPEC 01 registra **qué** cambió y **quién** lo cambió, pero no **por qué**. Cuando alguien mueve una tarea de fecha o le baja las cajas, el motivo se queda en WhatsApp o en la cabeza de quien lo hizo.

Las observaciones son el hueco que falta: texto libre asociado a la tarea, firmado y fechado. No son un campo editable de la tarea sino una bitácora acumulativa — dos personas pueden opinar sobre la misma tarea sin pisarse.

**Esta spec no toca nada de las SPEC 01 y 02.** Crear una observación no escribe en `weekly_plan_task_logs` ni dispara correo: no modifica la tarea, así que el `WeeklyPlanTaskObserver` ni se entera. Es un recurso paralelo que solo comparte la FK.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_observations` y su modelo `WeeklyPlanTaskObservation`.
- Relación `WeeklyPlanTask::observations()`.
- CRUD completo del recurso siguiendo la convención por capas del proyecto: Interface, Service, Provider, FormRequests, Resource y Controller.
- `apiResource` declarado **dentro de `routes/weeklyplantasks.php`**, sin crear archivo de rutas nuevo.
- Listado filtrado por `?weeklyPlanTaskId=`, obligatorio, ordenado de la más antigua a la más reciente.
- Regla de propiedad: solo el autor puede editar o borrar su observación. Cualquier otro usuario recibe **403**.
- Nueva clase `App\Errors\ForbiddenError` (403), que hoy no existe en `app/Errors/`.
- Registro del provider en `bootstrap/providers.php`.

**Fuera de alcance (para specs futuras):**

- Paginación del listado. Decisión explícita: el `index` devuelve la colección completa de la tarea, sin `?limit` ni `PaginatedResource`.
- Incluir las observaciones (o su conteo) dentro de `WeeklyPlanTaskResource`. El GET de la tarea **no cambia**.
- Registrar las observaciones en `weekly_plan_task_logs` (SPEC 01).
- Notificar por correo la creación de una observación (SPEC 02).
- Observaciones en `DraftWeeklyPlanTask` o en cualquier otro modelo. Nada de tabla polimórfica genérica.
- Adjuntos, imágenes, menciones a usuarios o respuestas anidadas.
- Historial de ediciones de una observación. Editar sobrescribe el texto; solo queda el flag de que fue editada.
- Restricciones por `status` de la tarea. Se puede observar una tarea finalizada.
- Restricciones por permiso o rol. Basta el JWT válido.
- Tests automatizados. El repositorio sigue sin carpeta `tests/` y esta spec no la crea.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_observations_table`

```php
Schema::create('weekly_plan_task_observations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->text('observation');
    $table->timestamps();
});
```

- `cascadeOnDelete` sobre la tarea, misma decisión que en la SPEC 01: borrar una tarea se lleva sus observaciones. Un `splitTask` borra la tarea original, así que sus observaciones **no** viajan a las tareas resultantes.
- `user_id` sin cascada: un usuario no se borra dejando observaciones huérfanas.
- `observation` es `text` y **no** es nullable.

### Modelo `WeeklyPlanTaskObservation`

```php
#[Fillable(['weekly_plan_task_id', 'user_id', 'observation'])]
class WeeklyPlanTaskObservation extends Model
```

Relaciones:

- `WeeklyPlanTaskObservation::task()` → `belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id')`.
- `WeeklyPlanTaskObservation::user()` → `belongsTo(User::class)`.
- `WeeklyPlanTask::observations()` → `hasMany(WeeklyPlanTaskObservation::class)`.

`WeeklyPlanTask` **no** añade la observación a `LOGGED_FIELDS` ni cambia su `#[Fillable]`. El único cambio en el modelo es el método `observations()`.

### Endpoints

Todos bajo `Route::middleware('jwt.auth')` en `routes/weeklyplantasks.php`:

```php
Route::apiResource('/weekly-plan-task-observations', WeeklyPlanTaskObservationsController::class);
```

| Método   | Ruta                                                     | Qué hace                                                   |
| -------- | -------------------------------------------------------- | ---------------------------------------------------------- |
| `GET`    | `/weekly-plan-task-observations?weeklyPlanTaskId={id}`   | Observaciones de esa tarea, `created_at ASC`                |
| `POST`   | `/weekly-plan-task-observations`                         | Crea una observación firmada por el usuario del JWT         |
| `GET`    | `/weekly-plan-task-observations/{id}`                    | Una observación concreta                                    |
| `PUT`    | `/weekly-plan-task-observations/{id}`                    | Edita el texto. Solo el autor                               |
| `DELETE` | `/weekly-plan-task-observations/{id}`                    | Borra la observación. Solo el autor                         |

`weeklyPlanTaskId` es **obligatorio** en el `index`: sin él se lanza `BadRequestError` con el mensaje `'El id de la tarea del plan semanal es obligatorio'`. No existe la consulta "todas las observaciones del sistema".

### Contrato de entrada

`CreateWeeklyPlanTaskObservationRequest`:

```php
'weekly_plan_task_id' => ['required', 'integer', 'exists:weekly_plan_tasks,id'],
'observation' =>         ['required', 'string', 'max:1000'],
```

`UpdateWeeklyPlanTaskObservationRequest`:

```php
'observation' => ['required', 'string', 'max:1000'],
```

El `weekly_plan_task_id` **no se puede cambiar** al editar: una observación no se muda de tarea. El `user_id` **nunca** llega por request, se toma de `auth()->user()->id`.

Mensajes en español, siguiendo la convención de los Requests existentes:

```php
'weekly_plan_task_id.required' => 'La tarea del plan semanal es obligatoria.',
'weekly_plan_task_id.integer' =>  'La tarea del plan semanal debe ser un número entero.',
'weekly_plan_task_id.exists' =>   'La tarea del plan semanal no existe.',
'observation.required' =>         'La observación es obligatoria.',
'observation.string' =>           'La observación debe ser una cadena de texto.',
'observation.max' =>              'La observación no puede superar los 1000 caracteres.',
```

### Contrato de salida

`WeeklyPlanTaskObservationResource`:

```php
[
    'id' => int,
    'weekly_plan_task_id' => int,
    'observation' => string,
    'user_id' => int,
    'user_name' => string,          // $this->user->name
    'created_at' => string,         // 'd-m-Y H:i'
    'updated_at' => string,         // 'd-m-Y H:i'
    'was_edited' => bool,           // updated_at != created_at
]
```

- `was_edited` se calcula comparando ambos timestamps, sin columna extra.
- **No** se devuelve `is_owner`: el frontend compara `user_id` con el usuario del token. Decisión explícita.
- **No** se devuelve contexto de SKU ni línea. Quien pide observaciones ya tiene la tarea cargada.
- El `index` siempre carga `with('user')` para evitar N+1 al resolver `user_name`.

### Regla de propiedad

En el servicio, no en el controlador ni en un policy:

```php
if ($observation->user_id !== auth()->user()->id) {
    throw new ForbiddenError('No puedes modificar una observación de otro usuario');
}
```

Se aplica en `updateWeeklyPlanTaskObservationById` y en `deleteWeeklyPlanTaskObservationById`. El `show` y el `index` **no** la aplican: leer las observaciones de otro es el punto de la funcionalidad.

Nueva clase `app/Errors/ForbiddenError.php`, misma forma que `BadRequestError`:

```php
class ForbiddenError extends ApiException
{
    public function getStatusCode(): int
    {
        return 403;
    }
}
```

---

## Plan de implementación

1. **Migración.** `php artisan make:migration create_weekly_plan_task_observations_table --no-interaction` con el esquema de arriba. Verificación: `php artisan migrate` corre sin errores y la tabla existe.
2. **Modelo.** `php artisan make:model WeeklyPlanTaskObservation --no-interaction`, con `#[Fillable([...])]` y las relaciones `task()` y `user()`. Añadir `observations()` a `WeeklyPlanTask`.
3. **`ForbiddenError`.** `php artisan make:class Errors/ForbiddenError --no-interaction`, extendiendo `ApiException` con `getStatusCode(): 403`. Verificación: `ResponseHandler::error()` lo traduce a una respuesta 403.
4. **Interface.** `app/Interfaces/WeeklyPlanTaskObservations/WeeklyPlanTaskObservationsServiceInterface.php` con los cinco métodos: `getWeeklyPlanTaskObservations(Request $request)`, `createWeeklyPlanTaskObservation(array $data)`, `getWeeklyPlanTaskObservationById(string $id)`, `updateWeeklyPlanTaskObservationById(array $data, string $id)`, `deleteWeeklyPlanTaskObservationById(string $id)`.
5. **Service.** `app/Services/WeeklyPlanTaskObservations/WeeklyPlanTaskObservationsService.php` implementando la interface con `#[Override]` en cada método. `createWeeklyPlanTaskObservation` inyecta `user_id` desde `auth()`. `getWeeklyPlanTaskObservationById` lanza `NotFoundError('La observación no existe')`. `update` y `delete` aplican la regla de propiedad antes de tocar nada.
6. **Provider.** `app/Providers/WeeklyPlanTaskObservations/WeeklyPlanTaskObservationsProvider.php` con el `bind`, registrado en `bootstrap/providers.php` en orden alfabético.
7. **Requests.** `php artisan make:request WeeklyPlanTaskObservations/CreateWeeklyPlanTaskObservationRequest --no-interaction` y su equivalente `Update...`, con las reglas y mensajes de arriba.
8. **Resource.** `php artisan make:resource WeeklyPlanTaskObservations/WeeklyPlanTaskObservationResource --no-interaction` con el contrato de salida.
9. **Controller.** `php artisan make:controller WeeklyPlanTaskObservationsController --api --no-interaction`, con el servicio inyectado por parámetro en cada método y todo envuelto en `try/catch` + `ResponseHandler`, igual que `WeeklyPlanTasksController`. Mensajes: `'Observaciones Obtenidas Correctamente'`, `'Observación Creada Correctamente'` (201), `'Observación Obtenida Correctamente'`, `'Observación Actualizada Correctamente'`, `'Observación Eliminada Correctamente'`.
10. **Rutas.** Añadir el `apiResource` al grupo `jwt.auth` existente de `routes/weeklyplantasks.php`. Verificación: `php artisan route:list --path=weekly-plan-task-observations` lista las cinco rutas.
11. **Formato.** Ejecutar `vendor/bin/pint --dirty --format agent`.

`WeeklyPlanTasksService`, `WeeklyPlanTasksController`, `WeeklyPlanTaskResource` y `WeeklyPlanTaskObserver` quedan **sin tocar**.

---

## Criterios de aceptación

Verificación manual contra los endpoints con JWT válido.

- [ ] `php artisan migrate` crea `weekly_plan_task_observations` con `weekly_plan_task_id`, `user_id`, `observation`, `created_at`, `updated_at`.
- [ ] `POST /weekly-plan-task-observations` con `weekly_plan_task_id` y `observation` devuelve **201** y guarda la fila con el `user_id` del token.
- [ ] El `user_id` guardado es el del JWT aunque el body incluya un `user_id` distinto.
- [ ] `POST` con `observation` vacío devuelve **422** con el mensaje `'La observación es obligatoria.'`.
- [ ] `POST` con `observation` de 1001 caracteres devuelve **422**.
- [ ] `POST` con un `weekly_plan_task_id` inexistente devuelve **422** con `'La tarea del plan semanal no existe.'`.
- [ ] `GET /weekly-plan-task-observations?weeklyPlanTaskId={id}` devuelve solo las observaciones de esa tarea, ordenadas de la **más antigua a la más reciente**, cada una con `user_name`.
- [ ] `GET /weekly-plan-task-observations` **sin** `weeklyPlanTaskId` devuelve **400**.
- [ ] `GET /weekly-plan-task-observations/{id}` de una observación de **otro** usuario devuelve **200**.
- [ ] `PUT /weekly-plan-task-observations/{id}` sobre una observación **propia** cambia el texto, devuelve **200** y deja `was_edited = true` en la siguiente lectura.
- [ ] `PUT /weekly-plan-task-observations/{id}` sobre una observación de **otro** usuario devuelve **403** y **no** modifica la fila.
- [ ] `DELETE /weekly-plan-task-observations/{id}` sobre una observación **propia** devuelve **200** y la fila desaparece.
- [ ] `DELETE /weekly-plan-task-observations/{id}` sobre una observación de **otro** usuario devuelve **403** y la fila sigue ahí.
- [ ] Cualquiera de las cinco rutas sin JWT devuelve **401**.
- [ ] Una observación recién creada tiene `was_edited = false`.
- [ ] `DELETE /weekly-plan-tasks/{id}` borra en cascada las observaciones de esa tarea, sin error de FK.
- [ ] Crear una observación **no** inserta ninguna fila en `weekly_plan_task_logs` y **no** genera correo.
- [ ] `GET /weekly-plan-tasks/{id}` devuelve exactamente el mismo JSON que antes de esta spec.
- [ ] Se puede añadir una observación a una tarea con `status = 5` (Finalizada).
- [ ] `vendor/bin/pint --test` no reporta archivos con formato incorrecto.

---

## Decisiones

- **Sí:** tabla `weekly_plan_task_observations` con varias filas por tarea. Cada observación lleva su autor y su fecha, y dos personas pueden comentar la misma tarea sin sobrescribirse.
- **No:** columna `observations` (text) en `weekly_plan_tasks`. Es más simple, pero una sola observación por tarea significa que el segundo usuario borra lo que escribió el primero, y no queda registro de quién la escribió.
- **Sí:** recurso propio con Interface, Service, Provider, Requests, Resource y Controller. Es la convención de todos los demás recursos del proyecto.
- **Sí:** rutas dentro de `routes/weeklyplantasks.php`, sin archivo de rutas nuevo. Decisión explícita del usuario: las observaciones son un satélite de las tareas, no un dominio aparte que justifique otro `require` en `api.php`.
- **Sí:** `apiResource` estándar con filtro por query param en el `index`. Es el mismo patrón que ya usa `getWeeklyPlanTasks` con `weeklyPlanId` y `operationDate`.
- **No:** ruta anidada `GET /weekly-plan-tasks/{id}/observations`. Habría obligado a partir el `apiResource` con un `except('index')` para no dejar dos rutas haciendo lo mismo.
- **Sí:** `weeklyPlanTaskId` obligatorio en el `index`. Nadie necesita todas las observaciones del sistema en una llamada, y así la consulta siempre va filtrada por tarea.
- **Sí:** orden `created_at ASC`. La bitácora se lee como una conversación, de arriba a abajo.
- **No:** paginación con `?limit`. Decisión explícita del usuario: el volumen esperado por tarea es de unas pocas filas; si crece, se pagina en otra spec.
- **Sí:** editar y borrar permitido, pero solo al autor. Es el punto medio entre la inmutabilidad total y que cualquiera pueda borrar el comentario de otro sin dejar rastro.
- **Sí:** la regla de propiedad vive en el Service. El proyecto no usa Policies y los controladores solo orquestan; meterla en el Service la deja en el mismo sitio donde ya viven las validaciones de negocio de `splitWeeklyPlanTask`.
- **Sí:** nueva clase `ForbiddenError` (403). Es el código semánticamente correcto y la clase se reutiliza en cualquier spec futura con reglas de propiedad.
- **No:** reusar `BadRequestError` (400) para el caso de propiedad. Ahorra un archivo a costa de que el frontend no pueda distinguir "datos mal enviados" de "no es tuyo".
- **Sí:** `user_id` tomado siempre de `auth()`, nunca del body. Una observación firmada por otro no cumple el objetivo.
- **Sí:** `was_edited` calculado desde los timestamps. Marca visualmente lo editado sin columna nueva ni tabla de historial.
- **No:** `is_owner` en el Resource. Decisión explícita del usuario: el frontend ya tiene el usuario del token y puede comparar `user_id`.
- **No:** historial de ediciones de la observación. Auditar el texto de un comentario editado es otra tabla y otra spec.
- **No:** enganchar las observaciones a la SPEC 01 (`weekly_plan_task_logs`) ni a la SPEC 02 (correo). Decisión explícita del usuario. La observación ya lleva autor y fecha propios, y no modifica la tarea, así que el observer ni se dispara.
- **No:** incluir las observaciones dentro de `WeeklyPlanTaskResource`. Ese Resource lo consumen el calendario y los listados; cargar `observations` en cada tarea de un plan semanal completo es peso que casi ninguna vista usa.
- **Sí:** `cascadeOnDelete` sobre la tarea. Misma decisión que la SPEC 01: sin observaciones huérfanas. Consecuencia asumida: un `splitTask` se lleva las observaciones de la tarea original.
- **Sí:** `max:1000` en el texto. La columna es `text` y aguanta mucho más, pero un tope explícito evita que alguien pegue un documento entero en la bitácora.
- **Sí:** sin restricción por `status`. Una observación es un comentario, no una edición de la planificación: comentar una tarea ya finalizada es legítimo.
- **No:** tests Pest. Decisión explícita del usuario; el repositorio sigue sin carpeta `tests/`, igual que en las SPEC 01 y 02.

---

## Riesgos

| Riesgo                                                                                          | Mitigación                                                                                                                          |
| ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Un `splitTask` borra la tarea original y con ella sus observaciones                             | Consecuencia asumida del `cascadeOnDelete`, igual que los logs de la SPEC 01. Si hay que conservarlas, es otra spec.                   |
| El `index` sin paginación crece sin techo si una tarea acumula cientos de observaciones         | Aceptado. El filtro por tarea es obligatorio, así que el peor caso está acotado a una sola tarea. Paginar es cambio aislado.           |
| `user_name` provoca N+1 si alguien olvida el `with('user')`                                     | El `with('user')` va en la consulta del Service, no en el controlador, así que ninguna llamada al `index` puede saltárselo.            |
| Un usuario borra su observación y desaparece el motivo de un cambio ya registrado en el log     | Aceptado. La observación es un comentario, no auditoría; la SPEC 01 sigue guardando el cambio en sí.                                    |
| `ForbiddenError` es la primera clase 403 del proyecto y `ResponseHandler` podría no mapearla     | Extiende `ApiException`, que es lo que `ResponseHandler` ya usa para resolver el status. Se verifica en el criterio de aceptación 403. |
| Texto libre sin sanitizar renderizado en el frontend                                            | El backend guarda y devuelve el texto tal cual; escapar en la vista es responsabilidad del frontend, como con `destination`.           |

---

## Lo que **no** entra en esta spec

- Paginación del listado de observaciones.
- Observaciones dentro de `WeeklyPlanTaskResource`.
- Enganche con `weekly_plan_task_logs` y con las notificaciones por correo.
- Adjuntos, menciones, respuestas anidadas e historial de ediciones.
- Observaciones en otros modelos y tabla polimórfica genérica.
- Tests automatizados y creación de la carpeta `tests/`.

Cada uno de esos, si llega, va en su propia spec.
