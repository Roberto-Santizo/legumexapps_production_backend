# SPEC 07 — Tiempos muertos de cada WeeklyPlanTask

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-10-02
> **Objetivo:** Registrar en vivo los tiempos muertos (`Timeout`) que ocurren durante la ejecución de una `WeeklyPlanTask`, con hora de inicio y de fin puestas por el servidor.

---

## Por qué existe esta spec

Hoy existe el catálogo `timeouts` (tipos de percance: falla mecánica, falta de material, etc.), pero no hay forma de decir que una tarea en progreso estuvo detenida, por qué y cuánto tiempo.

Flujo del usuario:

1. La tarea está "En Progreso" (status `4`).
2. Ocurre un percance. El usuario elige el tipo de `Timeout`, opcionalmente escribe una observación y lo **abre**. El servidor guarda `start_date = now()`.
3. Cuando el percance termina, el usuario lo **cierra**. Puede agregar o cambiar la observación. El servidor guarda `end_date = now()` y la duración en horas.
4. Solo puede haber un tiempo muerto abierto a la vez por tarea.
5. La tarea no se puede finalizar mientras tenga un tiempo muerto abierto.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_timeouts`, modelo `WeeklyPlanTaskTimeout` y relaciones.
- Feature `WeeklyPlanTaskTimeouts` con su Interface, Service, Provider, Requests, Resource y Controller.
- `GET /weekly-plan-tasks/{id}/timeouts`: tiempos muertos de la tarea.
- `POST /weekly-plan-tasks/{id}/startTimeout`: abre un tiempo muerto.
- `POST /weekly-plan-task-timeouts/{id}/end`: cierra un tiempo muerto.
- `PATCH /weekly-plan-task-timeouts/{id}`: edita tipo (`timeout_id`) y observación.
- `DELETE /weekly-plan-task-timeouts/{id}`: elimina un tiempo muerto (borrado físico).
- Todas las escrituras solo con la tarea en status `4`.
- Bloqueo en `WeeklyPlanTasksService::endWeeklyPlanTask` si hay un tiempo muerto abierto.
- Campos `timeout_hours` y `open_timeout_id` en `WeeklyPlanTaskResource`.
- Bloqueo en `TimeoutsService::deleteTimeoutById` si el tipo se usó en alguna tarea.
- Documentación de los endpoints nuevos y los campos nuevos en `public/openapi.yaml`.
- Guía de integración para frontend en `references/weekly-plan-task-timeouts.md`.

**Fuera de alcance (para specs futuras):**

- Capturar o editar `start_date` / `end_date` a mano. Las horas siempre las pone el servidor.
- Registrar tiempos muertos en status distinto de `4` (antes de iniciar o después de finalizar).
- Tiempos muertos simultáneos en la misma tarea.
- Cierre automático de tiempos muertos abiertos al finalizar la tarea.
- Descontar el tiempo muerto de `hours`, del rendimiento o de cualquier cálculo de productividad.
- Reportes o totales por tipo de `Timeout`, por línea o por plan.
- `user_id` de quien cierra el tiempo muerto.
- Soft delete o historial de cambios de los tiempos muertos.
- Registrar los tiempos muertos en `weekly_plan_task_logs` o enviarlos por correo.
- Paginación del listado. Una tarea tiene pocos tiempos muertos.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_timeouts_table`

```php
Schema::create('weekly_plan_task_timeouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('timeout_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->timestamp('start_date');
    $table->timestamp('end_date')->nullable();
    $table->float('duration_hours')->nullable();
    $table->string('observation', 500)->nullable();
    $table->timestamps();

    $table->index(['weekly_plan_task_id', 'end_date']);
});
```

| Columna            | Significado                                                                          |
| ------------------ | ------------------------------------------------------------------------------------ |
| `timeout_id`       | Tipo de percance, del catálogo `timeouts`. Sin cascade: el tipo no se borra si está en uso. |
| `user_id`          | Quién abrió el tiempo muerto (`auth()->user()`).                                      |
| `start_date`       | `now()` al abrir.                                                                    |
| `end_date`         | `now()` al cerrar. `null` = tiempo muerto abierto.                                    |
| `duration_hours`   | Horas entre `start_date` y `end_date`, redondeado a 4 decimales. `null` mientras está abierto. |
| `observation`      | Texto libre opcional. Se puede mandar al abrir, al cerrar o al editar.                |

`duration_hours` se guarda al cerrar, igual que `theoretical_pounds` / `difference_pounds` en `weekly_plan_task_performance_records`. Así el total por tarea es un `withSum`, sin aritmética de fechas en SQL.

### Modelo `App\Models\WeeklyPlanTaskTimeout`

- Fillable: `weekly_plan_task_id`, `timeout_id`, `user_id`, `start_date`, `end_date`, `duration_hours`, `observation`.
- Casts: `start_date` y `end_date` → `datetime`.
- Relaciones: `task()` → `WeeklyPlanTask` (`weekly_plan_task_id`), `timeout()` → `Timeout`, `user()` → `User`.

Relaciones nuevas en modelos existentes:

- `WeeklyPlanTask::timeouts()` → `hasMany(WeeklyPlanTaskTimeout::class)`.
- `WeeklyPlanTask::openTimeout()` → `hasOne(WeeklyPlanTaskTimeout::class)->whereNull('end_date')`.
- `Timeout::taskTimeouts()` → `hasMany(WeeklyPlanTaskTimeout::class)`.

### Endpoints

Todos bajo `jwt.auth`, en `routes/weeklyplantasks.php`, en el grupo `// FUNCTIONALITYS`. Los atiende `WeeklyPlanTaskTimeoutsController`.

| Método   | Ruta                                       | Body                                      | Respuesta                                                   |
| -------- | ------------------------------------------ | ----------------------------------------- | ----------------------------------------------------------- |
| `GET`    | `/weekly-plan-tasks/{id}/timeouts`         | —                                         | 200, `WeeklyPlanTaskTimeoutResource::collection`            |
| `POST`   | `/weekly-plan-tasks/{id}/startTimeout`     | `timeout_id`, `observation?`              | 201, `WeeklyPlanTaskTimeoutResource` del creado             |
| `POST`   | `/weekly-plan-task-timeouts/{id}/end`      | `observation?`                            | 200, `WeeklyPlanTaskTimeoutResource` del cerrado            |
| `PATCH`  | `/weekly-plan-task-timeouts/{id}`          | `timeout_id?`, `observation?`             | 200, `data: null`                                           |
| `DELETE` | `/weekly-plan-task-timeouts/{id}`          | —                                         | 200, `data: null`                                           |

Mensajes de éxito:

- Listado: `Tiempos Muertos Obtenidos Correctamente`.
- Abrir: `Tiempo Muerto Iniciado Correctamente`.
- Cerrar: `Tiempo Muerto Finalizado Correctamente`.
- Editar: `Tiempo Muerto Actualizado Correctamente`.
- Eliminar: `Tiempo Muerto Eliminado Correctamente`.

### Requests

`StartWeeklyPlanTaskTimeoutRequest`:

```text
timeout_id    integer  required|exists:timeouts,id
observation   string   nullable|max:500
```

`EndWeeklyPlanTaskTimeoutRequest`:

```text
observation   string   nullable|max:500
```

`UpdateWeeklyPlanTaskTimeoutRequest`:

```text
timeout_id    integer  sometimes|required|exists:timeouts,id
observation   string   sometimes|nullable|max:500
```

Mensajes de validación en español, con el estilo de `CreateWeeklyPlanTaskPerformanceRecordRequest`.

### Reglas de negocio

El Service depende de `WeeklyPlanTasksServiceInterface` (para `getWeeklyPlanTaskById` y su `NotFoundError`), igual que `WeeklyPlanTaskPerformanceRecordsService`.

- Tarea inexistente → `NotFoundError` (reutiliza `getWeeklyPlanTaskById`).
- Tiempo muerto inexistente → `NotFoundError('El tiempo muerto no existe')`.
- Cualquier escritura (abrir, cerrar, editar, eliminar) con la tarea en status distinto de `4` → `BadRequestError('Solo se pueden registrar tiempos muertos en una tarea en progreso')`.
- **Abrir:** si la tarea ya tiene un tiempo muerto abierto → `BadRequestError('La tarea ya tiene un tiempo muerto abierto')`. Dentro de `DB::transaction`, relee la tarea con `lockForUpdate()` y revalida status `4` y que no haya uno abierto antes de crear. Crea la fila con `user_id = auth()->user()->id` y `start_date = now()`.
- **Cerrar:** si `end_date` ya tiene valor → `BadRequestError('El tiempo muerto ya fue finalizado')`. Pone `end_date = now()` y `duration_hours = round(start_date->diffInHours(end_date), 4)`. Si el body trae `observation` no nula, reemplaza la anterior; si no, la conserva.
- **Editar:** solo cambia `timeout_id` y `observation`. Se permite sobre abiertos y cerrados. `observation: null` explícito la borra.
- **Eliminar:** borrado físico. Se permite sobre abiertos y cerrados.

### Cambio en `WeeklyPlanTasksService::endWeeklyPlanTask`

Dentro de la transacción de `transitionWeeklyPlanTask`, con la tarea ya bloqueada (`lockForUpdate()`), si `openTimeout` existe → `BadRequestError('No se puede finalizar la tarea con un tiempo muerto abierto')`. La validación va bajo el mismo lock que la apertura para que no se cruce con un `startTimeout` concurrente.

### Cambio en `WeeklyPlanTaskResource`

Dos campos nuevos:

```php
'timeout_hours' => round((float) ($this->timeouts_sum_duration_hours ?? 0), 2),
'open_timeout_id' => $this->openTimeout?->id,
```

`timeout_hours` suma solo los cerrados (los abiertos tienen `duration_hours = null`). Cada lugar del Service que devuelve tareas para este Resource agrega `withSum('timeouts', 'duration_hours')` y `with('openTimeout')`, junto al `withSum('performanceRecords', 'weighed_pounds')` existente:

- `getWeeklyPlanTasks`.
- `getWeeklyPlanTaskById`.
- `splitWeeklyPlanTask` (consulta final).
- `transitionWeeklyPlanTask` (el `fresh()` final de `start` y `end`).

Sin eso, `open_timeout_id` dispara una consulta por tarea en el listado.

### `WeeklyPlanTaskTimeoutResource`

```php
[
    'id' => $this->id,
    'weekly_plan_task_id' => $this->weekly_plan_task_id,
    'timeout_id' => $this->timeout_id,
    'timeout_name' => $this->timeout->name,
    'user_id' => $this->user_id,
    'user_name' => $this->user->name,
    'start_date' => $this->start_date,
    'end_date' => $this->end_date,
    'duration_hours' => $this->duration_hours !== null ? round($this->duration_hours, 2) : null,
    'observation' => $this->observation,
    'is_open' => $this->end_date === null,
]
```

El listado se ordena por `start_date` y carga `with(['timeout', 'user'])` para evitar N+1.

### Cambio en `TimeoutsService::deleteTimeoutById`

Si el `Timeout` tiene filas en `weekly_plan_task_timeouts` → `BadRequestError('No se puede eliminar un tiempo muerto registrado en tareas')`. Sin esto, la FK lanza un error SQL (500).

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración y modelo.** `php artisan make:model WeeklyPlanTaskTimeout -m --no-interaction`. Columnas, FKs e índice del modelo de datos. Fillable, casts y relaciones del modelo nuevo. `timeouts()` y `openTimeout()` en `WeeklyPlanTask`; `taskTimeouts()` en `Timeout`. Verificación: `php artisan migrate` sin error y `database-schema` muestra la tabla.
2. **Esqueleto de la feature.** `WeeklyPlanTaskTimeoutsServiceInterface`, `WeeklyPlanTaskTimeoutsService` (inyecta `WeeklyPlanTasksServiceInterface`), `WeeklyPlanTaskTimeoutsProvider` registrado en `bootstrap/providers.php`, `WeeklyPlanTaskTimeoutsController` vacío y `WeeklyPlanTaskTimeoutResource`. Verificación: `php artisan route:list` no falla.
3. **Listado.** `getTaskTimeouts(string $taskId)` y ruta `GET /weekly-plan-tasks/{id}/timeouts`. Verificación: tarea sin tiempos muertos → `data: []`.
4. **Abrir.** `StartWeeklyPlanTaskTimeoutRequest`, `startTimeout(string $taskId, array $data)` y ruta `POST /weekly-plan-tasks/{id}/startTimeout`, con status `4`, uno abierto a la vez y `lockForUpdate()`.
5. **Cerrar.** `EndWeeklyPlanTaskTimeoutRequest`, `endTimeout(string $id, array $data)` y ruta `POST /weekly-plan-task-timeouts/{id}/end`, con cálculo de `duration_hours`.
6. **Editar y eliminar.** `UpdateWeeklyPlanTaskTimeoutRequest`, `updateTimeout(string $id, array $data)`, `deleteTimeout(string $id)` y rutas `PATCH` / `DELETE /weekly-plan-task-timeouts/{id}`.
7. **Bloqueo al finalizar la tarea.** Validación de `openTimeout` dentro del lock de `endWeeklyPlanTask`.
8. **Campos en `WeeklyPlanTaskResource`.** `timeout_hours` y `open_timeout_id`, con `withSum` y `with('openTimeout')` en las cuatro consultas listadas.
9. **Protección al borrar `Timeout`.** Validación en `TimeoutsService::deleteTimeoutById`.
10. **Documentación.** Endpoints nuevos, schema de `WeeklyPlanTaskTimeout`, campos nuevos de la tarea y el 400 nuevo de `/weekly-plan-tasks/{id}/end` en `public/openapi.yaml`.
11. **Guía de integración para frontend.** Crear `references/weekly-plan-task-timeouts.md` con la estructura de `references/weekly-plan-task-performance-records.md`: resumen de endpoints, flujo y reglas (status `4`, uno abierto a la vez, bloqueo de fin de tarea), tipos TypeScript, cada endpoint con request y respuesta de ejemplo, campos `timeout_hours` / `open_timeout_id` en la tarea, errores (400, 404, 422 con sus mensajes), ejemplo de uso y checklist para el frontend.
12. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `weekly_plan_task_timeouts` con las 3 FKs y el índice `(weekly_plan_task_id, end_date)`.
- [ ] `php artisan route:list --path=weekly-plan-task` muestra las 5 rutas nuevas bajo `jwt.auth`.
- [ ] `startTimeout` en una tarea con status `4` crea una fila con `start_date` del servidor, `end_date = null`, `user_id` del usuario autenticado y responde 201 con `is_open: true`.
- [ ] `startTimeout` con status `1`, `2`, `3` o `5` → 400 `Solo se pueden registrar tiempos muertos en una tarea en progreso` y no crea fila.
- [ ] `startTimeout` con un tiempo muerto abierto en la tarea → 400 `La tarea ya tiene un tiempo muerto abierto`.
- [ ] `startTimeout` con `timeout_id` inexistente → 422 con mensaje en español.
- [ ] `end` pone `end_date` del servidor y `duration_hours` igual a las horas transcurridas (4 decimales en BD; 2 en el Resource).
- [ ] `end` sobre un tiempo muerto ya cerrado → 400 `El tiempo muerto ya fue finalizado`.
- [ ] `end` con `observation` la reemplaza; sin `observation` conserva la de la apertura.
- [ ] Después de cerrar, `startTimeout` vuelve a permitirse en la misma tarea.
- [ ] `PATCH` cambia `timeout_id` y `observation` sin tocar `start_date`, `end_date` ni `duration_hours`.
- [ ] `DELETE` borra la fila de BD; después `GET /weekly-plan-tasks/{id}/timeouts` no la incluye.
- [ ] `end`, `PATCH` y `DELETE` con la tarea fuera de status `4` → 400 con el mensaje de la spec.
- [ ] Id de tiempo muerto inexistente en `end`, `PATCH` o `DELETE` → 404 `El tiempo muerto no existe`.
- [ ] `POST /weekly-plan-tasks/{id}/end` con un tiempo muerto abierto → 400 `No se puede finalizar la tarea con un tiempo muerto abierto` y la tarea sigue en status `4`.
- [ ] `WeeklyPlanTaskResource` devuelve `timeout_hours` igual a la suma de `duration_hours` de los cerrados y `open_timeout_id` con el id del abierto o `null`, en `index`, `show`, `splitTask`, `start` y `end`.
- [ ] `GET /weekly-plan-tasks` no hace consultas N+1 por `open_timeout_id`.
- [ ] `GET /weekly-plan-tasks/{id}/timeouts` no hace consultas N+1 (una consulta por relación cargada).
- [ ] Borrar un `Timeout` usado en alguna tarea → 400 `No se puede eliminar un tiempo muerto registrado en tareas`, no 500.
- [ ] Borrar una `WeeklyPlanTask` borra sus filas de `weekly_plan_task_timeouts`.
- [ ] `public/openapi.yaml` documenta las 5 rutas nuevas y los campos `timeout_hours` / `open_timeout_id`.
- [ ] `references/weekly-plan-task-timeouts.md` existe con las secciones de la guía de integración y cubre los 5 endpoints, los mensajes de error y los campos nuevos de la tarea.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** flujo en dos pasos (abrir / cerrar) con horas del servidor. Igual que `start` / `end` de la tarea; el usuario no puede falsear horas.
- **No:** capturar `start_date` / `end_date` en el body. Abre la puerta a horas inventadas y a traslapes difíciles de validar.
- **Sí:** un solo tiempo muerto abierto por tarea. Evita duraciones dobles cuando una línea está detenida.
- **Sí:** solo en status `4`. Un percance solo tiene sentido mientras la tarea se ejecuta.
- **Sí:** bloquear el fin de la tarea si hay uno abierto. Obliga a cerrar con la hora real en lugar de inventar una.
- **No:** cerrar automáticamente al finalizar la tarea. La hora de cierre sería la del fin de la tarea, no la del fin del percance.
- **Sí:** `user_id` de quien abre. Mismo criterio que `weekly_plan_task_performance_records`.
- **No:** `user_id` de quien cierra. No se pidió; se agrega en otra spec si hace falta.
- **Sí:** observación opcional al abrir, al cerrar y al editar. La causa real muchas veces se conoce al terminar.
- **Sí:** editar solo `timeout_id` y `observation`. Corrige un tipo mal elegido sin tocar horas.
- **Sí:** eliminar con borrado físico. Igual que las tomas de rendimiento; sirve para registros abiertos por error.
- **No:** `SoftDeletes`. No se pidió historial de tiempos muertos borrados.
- **Sí:** guardar `duration_hours` al cerrar. Permite `withSum` en el listado de tareas; mismo patrón que las libras calculadas de rendimiento.
- **Sí:** duración en horas, no en minutos. Misma unidad que `hours` de la tarea.
- **Sí:** 4 decimales en BD y 2 en los Resources. Con 2 decimales en BD, un percance de 1 minuto (0.0167 h) se guardaría como 0.02 y la suma por tarea acumularía error.
- **No:** calcular la duración en SQL con diferencia de fechas. Depende del motor (`pgsql` en `.env`, `sqlite` en `.env.example`).
- **Sí:** `timeout_hours` y `open_timeout_id` en `WeeklyPlanTaskResource`. El frontend sabe si hay un percance en curso sin otra petición.
- **Sí:** `openTimeout()` como `hasOne` con `whereNull('end_date')`. Se carga con `with` y evita N+1.
- **Sí:** abrir devuelve el Resource creado. El frontend necesita el `id` para cerrarlo.
- **Sí:** rutas camelCase bajo `/weekly-plan-tasks/{id}/…` y `/weekly-plan-task-timeouts/{id}`, igual que SPEC 06.
- **No:** `apiResource` para `weekly-plan-task-timeouts`. Crear sigue reglas del flujo (status, uno abierto, horas del servidor).
- **Sí:** `cascadeOnDelete` desde la tarea. Si se borra la tarea, sus tiempos muertos no tienen a qué apuntar.
- **Sí:** bloquear el borrado de un `Timeout` en uso. Sin esto la FK devuelve 500 y se perdería el tipo de los registros históricos.
- **No:** tests automatizados. Consistente con SPEC 01–06; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Dos usuarios abren un tiempo muerto en la misma tarea al mismo tiempo. | `startTimeout` relee la tarea con `lockForUpdate()` y revalida que no haya uno abierto antes de crear. |
| Un usuario abre un tiempo muerto mientras otro finaliza la tarea. | Ambas operaciones bloquean la fila de la tarea; la segunda ve el estado final y falla con 400. |
| Un tiempo muerto queda abierto por olvido y bloquea el fin de la tarea. | El usuario lo cierra (la duración será larga) o lo elimina. No hay cierre automático. |
| Las tareas que ya estaban en status `4` antes de esta spec no tienen tiempos muertos. | Se acepta: `timeout_hours = 0` y `open_timeout_id = null`. |
| Faltan `withSum` / `with('openTimeout')` en alguna consulta que alimenta `WeeklyPlanTaskResource`. | `timeout_hours` cae a `0` por el `?? 0`, pero `open_timeout_id` haría lazy load. Los criterios de aceptación revisan los 5 endpoints. |
| `WeeklyPlanTaskObserver` no ve los tiempos muertos. | Se acepta: no generan log ni correo en esta spec. |

---

## Lo que **no** está en esta spec

- Horas de inicio o fin manuales.
- Tiempos muertos fuera de status `4`.
- Tiempos muertos simultáneos en una tarea.
- Cierre automático al finalizar la tarea.
- Descontar tiempo muerto de horas o rendimiento.
- Reportes por tipo, línea o plan.
- Usuario que cierra el tiempo muerto.
- Soft delete o historial de tiempos muertos.
- Logs o correos de tiempos muertos.
- Paginación del listado.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
