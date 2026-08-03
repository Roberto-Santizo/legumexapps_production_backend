# SPEC 01 — Registro de cambios de WeeklyPlanTask

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-08-03
> **Objetivo:** Persistir en base de datos cada creación y cada cambio de `boxes`, `operation_date` y `line_sku_id` de una `WeeklyPlanTask`, junto al `user_id` que lo provocó.

---

## Por qué existe esta spec

Hoy no hay forma de saber quién movió una tarea de fecha, quién le cambió las cajas o quién la reasignó a otra línea. Cuando el plan semanal no cuadra, la discusión se resuelve de memoria.

El registro se hace con un **observer** de `WeeklyPlanTask`, no con escrituras explícitas en el servicio. Es el primer observer del proyecto. La condición que lo hace viable es el refactor de `assignOperationDate`: al pasar de update masivo a guardado tarea por tarea, ya no queda ninguna escritura que se salte los eventos de modelo, así que el observer captura el 100% de los cambios sin depender de que alguien se acuerde de llamar al log en cada método nuevo.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_logs` y su modelo `WeeklyPlanTaskLog`.
- `WeeklyPlanTaskObserver`, registrado en el modelo con `#[ObservedBy]`, que escucha `creating`, `created`, `updating` y `updated`.
- Registro del evento `created` al crear una tarea (una sola fila, sin detalle de campos).
- Registro del evento `updated` con **una fila por campo modificado**, limitado a `boxes`, `operation_date` y `line_sku_id`.
- Escritura del `user_id` autenticado en cada log; si no hay usuario autenticado, la operación se aborta **antes** de tocar la tabla `weekly_plan_tasks`.
- Refactor de `assignOperationDate` de update masivo a guardado tarea por tarea dentro de una transacción, requisito para que los eventos de modelo se disparen.

**Fuera de alcance (para specs futuras):**

- Endpoint de consulta del historial (`GET /weekly-plan-tasks/{id}/logs`), Resource y paginación.
- Registro del evento `deleted`. Se descartó porque la FK es `cascadeOnDelete` y el log se borraría en el mismo instante en que se escribe.
- Registro de los demás campos de la tarea: `produced_boxes`, `produced_pallets`, `hours`, `pallets`, `weighed_pounds`, `destination`, `start_date`, `end_date`, `weekly_plan_id`, `status`.
- Tabla de auditoría genérica polimórfica reutilizable por otros modelos.
- Observers para cualquier otro modelo del proyecto.
- Tests automatizados. El repositorio no tiene carpeta `tests/` y esta spec no la crea.
- Trazar en el log la relación entre la tarea original de un `splitTask` y las tareas resultantes.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_logs_table`

```php
Schema::create('weekly_plan_task_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('event');                    // 'created' | 'updated'
    $table->string('field')->nullable();        // null en 'created'
    $table->text('old_value')->nullable();
    $table->text('new_value')->nullable();
    $table->timestamps();
});
```

### Filas que se generan

| Evento    | Filas                                    | `field`                                       | `old_value` / `new_value`             |
| --------- | ---------------------------------------- | --------------------------------------------- | ------------------------------------- |
| `created` | Exactamente 1                            | `null`                                        | `null` / `null`                       |
| `updated` | Una por campo rastreado que cambió (0–3) | `boxes`, `operation_date` o `line_sku_id`     | Valor anterior / valor nuevo, en texto |

Convenciones:

- `old_value` y `new_value` se guardan como texto. Los `null` reales (por ejemplo una `operation_date` que no estaba asignada) se guardan como `null` en la columna.
- Un `update` que no toca ninguno de los tres campos rastreados **no genera ninguna fila**.
- Un `update` que envía un campo rastreado con el mismo valor que ya tenía **no genera fila**: solo se registra el cambio efectivo.

### Campos rastreados

Constante pública en `WeeklyPlanTask`:

```php
public const LOGGED_FIELDS = ['boxes', 'operation_date', 'line_sku_id'];
```

### Relaciones

- `WeeklyPlanTask::logs()` → `hasMany(WeeklyPlanTaskLog::class)`.
- `WeeklyPlanTaskLog::task()` → `belongsTo(WeeklyPlanTask::class, 'weekly_plan_task_id')`.
- `WeeklyPlanTaskLog::user()` → `belongsTo(User::class)`.

### Reparto de responsabilidades del observer

`app/Observers/WeeklyPlanTaskObserver.php`, enganchado con `#[ObservedBy(WeeklyPlanTaskObserver::class)]` sobre `WeeklyPlanTask` (misma convención de atributos que ya usa `#[Fillable]`).

| Hook       | Qué hace                                                                                                                              |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `creating` | Si `auth()->user()` es `null`, lanza `BadRequestError`. Aborta el insert.                                                              |
| `created`  | Escribe una fila `event = 'created'`, `field = null`.                                                                                  |
| `updating` | Si `auth()->user()` es `null` **y** `getDirty()` toca algún `LOGGED_FIELDS`, lanza `BadRequestError`. Aborta el update.                |
| `updated`  | Recorre `getChanges()`, filtra por `LOGGED_FIELDS` y escribe una fila por campo, tomando el valor anterior de `getOriginal($campo)`.   |

La validación del usuario va en los hooks `-ing` a propósito: lanzar antes del guardado aborta la escritura sin necesidad de envolver nada en una transacción.

---

## Plan de implementación

1. **Migración.** `php artisan make:migration create_weekly_plan_task_logs_table` con el esquema de arriba. Verificación: `php artisan migrate` corre sin errores y la tabla existe.
2. **Modelo `WeeklyPlanTaskLog`.** `php artisan make:model WeeklyPlanTaskLog --no-interaction`, con el atributo `#[Fillable([...])]` (convención del proyecto, igual que `PackingMaterialTransaction`) y las relaciones `task()` y `user()`.
3. **Constante y relación en `WeeklyPlanTask`.** Añadir `LOGGED_FIELDS` y el método `logs()`.
4. **Refactor de `assignOperationDate`.** Sustituir el `whereIn(...)->update(...)` por una transacción que recorre las tareas obtenidas con `whereIn('id', $tasksIds)->get()` y guarda cada una con `$task->update(['operation_date' => $operationDate])`, de modo que dispare eventos de modelo. La firma del método y su valor de retorno (`true`) no cambian. Verificación: el endpoint sigue asignando la fecha a todas las tareas enviadas.
5. **Observer.** `php artisan make:observer WeeklyPlanTaskObserver --model=WeeklyPlanTask --no-interaction`, implementando los cuatro hooks de la tabla de arriba. El mensaje del error es `'No hay un usuario autenticado para registrar el cambio'`.
6. **Registro del observer.** Añadir `#[ObservedBy(WeeklyPlanTaskObserver::class)]` sobre la clase `WeeklyPlanTask`. Verificación: crear una tarea vía `POST /weekly-plan-tasks` deja una fila en `weekly_plan_task_logs`.
7. **Formato.** Ejecutar `vendor/bin/pint --dirty --format agent`.

`WeeklyPlanTasksService` no escribe logs en ningún punto: `createWeeklyPlanTask`, `updateWeeklyPlanTaskById` y `splitWeeklyPlanTask` quedan **sin tocar** más allá del refactor del paso 4.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `weekly_plan_task_logs` con las columnas `weekly_plan_task_id`, `user_id`, `event`, `field`, `old_value`, `new_value`, `created_at`, `updated_at`.
- [ ] `POST /weekly-plan-tasks` con JWT válido inserta exactamente **una** fila con `event = 'created'`, `field = null` y el `user_id` del token.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando `boxes` de 100 a 120 inserta una fila con `event = 'updated'`, `field = 'boxes'`, `old_value = '100'`, `new_value = '120'`.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando `boxes` y `operation_date` a la vez inserta exactamente **dos** filas.
- [ ] `PUT /weekly-plan-tasks/{id}` cambiando solo `produced_boxes` **no** inserta ninguna fila.
- [ ] `PUT /weekly-plan-tasks/{id}` enviando `boxes` con el mismo valor que ya tenía **no** inserta ninguna fila.
- [ ] `POST /weekly-plan-tasks/assignOperationDate` con 3 ids inserta 3 filas con `field = 'operation_date'` y el `old_value` correcto e individual de cada tarea.
- [ ] `POST /weekly-plan-tasks/splitTask` que divide una tarea en 2 inserta 2 filas `created`, y los logs de la tarea original desaparecen junto con ella.
- [ ] Crear o actualizar una tarea desde tinker sin usuario autenticado lanza `BadRequestError` y **no** deja la tarea creada ni actualizada.
- [ ] Actualizar desde tinker sin usuario autenticado un campo **no** rastreado (por ejemplo `produced_boxes`) funciona sin lanzar error.
- [ ] Borrar una tarea elimina sus filas en `weekly_plan_task_logs` (cascada) sin error de FK.
- [ ] `vendor/bin/pint --test` no reporta archivos con formato incorrecto.

---

## Decisiones

- **Sí:** `WeeklyPlanTaskObserver`. Una vez que `assignOperationDate` guarda tarea por tarea, no queda ninguna escritura que evada los eventos de modelo, y ningún método futuro puede olvidarse de registrar el cambio.
- **No:** escritura explícita en cada método de `WeeklyPlanTasksService`. Da control total pero depende de la disciplina de quien añada el siguiente método, y duplica la misma lógica en cuatro sitios.
- **Sí:** validar el usuario en `creating` / `updating`, no en `created` / `updated`. Lanzar antes del guardado aborta la operación sin necesidad de transacciones extra.
- **Sí:** `#[ObservedBy]` sobre el modelo en vez de registrar el observer en `AppServiceProvider`. Coincide con el estilo de atributos que el proyecto ya usa (`#[Fillable]`).
- **Sí:** una fila por campo cambiado. Consultar quién cambió un campo concreto es un `where` directo, sin parsear JSON.
- **No:** columnas JSON `old_values` / `new_values`. Menos filas, pero filtrar por campo exige consultas JSON.
- **Sí:** tabla específica `weekly_plan_task_logs` con FK real. Es el único modelo que necesita esto hoy.
- **No:** tabla polimórfica genérica de auditoría. Abstracción para un caso que hoy es uno solo; si aparece un segundo modelo, se generaliza en otra spec.
- **Sí:** `cascadeOnDelete`. Integridad referencial estricta: sin tareas huérfanas en el historial.
- **No:** registrar el evento `deleted`. Es incompatible con la cascada — la fila se borraría en el mismo momento en que se escribe. Auditar borrados requiere quitar la FK o añadir `SoftDeletes`, y ambas cosas se dejan para otra spec.
- **Sí:** `user_id` obligatorio, con error si no hay sesión. Un log sin autor no cumple el objetivo de la spec.
- **No:** `user_id` nullable. Evitaría romper seeders y comandos, pero abre la puerta a historial sin responsable.
- **Sí:** refactorizar `assignOperationDate` a guardado por tarea. Es el requisito para que el observer vea esos cambios; ahorrar N queries no compensa perder el registro en una operación que mueve pocas decenas de tareas.
- **Sí:** una sola fila en `created`. El estado inicial de la tarea ya está en `weekly_plan_tasks`; ocho filas por alta serían ruido.
- **Sí:** solo `boxes`, `operation_date` y `line_sku_id`. Son los tres campos sobre los que se discute la planificación.
- **No:** tests Pest. Decisión explícita del usuario; el repositorio no tiene infraestructura de tests todavía.

---

## Riesgos

| Riesgo                                                                                    | Mitigación                                                                                                             |
| ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Seeders, comandos artisan o jobs que creen o actualicen tareas empezarán a fallar         | El error es explícito (`BadRequestError` con mensaje claro). Si aparece un caso legítimo, se resuelve en otra spec.     |
| Un update masivo futuro (`whereIn(...)->update(...)`) se salta los eventos y no deja log  | Es la única vía de escape que queda. La regla: si el update toca `LOGGED_FIELDS`, se itera y se guarda modelo a modelo. |
| `app/Observers/` es una carpeta base nueva dentro de `app/`                               | Es la ubicación estándar que genera `php artisan make:observer`. No se inventa estructura propia.                       |
| La tabla crece sin política de retención                                                  | Aceptado. El volumen esperado (tareas por plan semanal) es bajo; la purga se define cuando el tamaño lo justifique.     |

---

## Lo que **no** entra en esta spec

- Endpoint de consulta del historial y su Resource.
- Evento `deleted` y auditoría de borrados.
- Registro de campos distintos de `boxes`, `operation_date` y `line_sku_id`.
- Tabla de auditoría genérica para otros modelos.
- Tests automatizados y creación de la carpeta `tests/`.

Cada uno de esos, si llega, va en su propia spec.
