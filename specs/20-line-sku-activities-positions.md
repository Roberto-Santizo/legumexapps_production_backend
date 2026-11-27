# SPEC 20 — Actividades y posiciones por SKU de línea

> **Estado:** Aprobado
> **Depende de:** SPEC 14, SPEC 15, SPEC 16, SPEC 17
> **Fecha:** 2026-11-27
> **Objetivo:** Mover los cupos por actividad (`line_activities` → `line_sku_activities`) y las posiciones (`positions`) de `Line` a `LineSku`, para que cada SKU de una línea tenga su propia dotación y sus propios puestos.

---

## Por qué existe esta spec

Hoy los cupos (`line_activities`, SPEC 14) y las posiciones (`positions`, SPEC 17) cuelgan de `Line`. Todos los SKUs que se producen en una línea comparten la misma configuración. En la planta no es así: el mismo `1PIQ1A` puede necesitar 10 recortadores para un SKU y 6 para otro.

`WeeklyPlanTask` ya apunta a un `LineSku` (`line_sku_id`, relación `performance()`). Llevar la configuración a `LineSku` la deja exactamente donde la tarea la busca.

Decisiones del usuario que dan forma a la spec:

- Los cupos se renombran: modelo `LineSkuActivity`, tabla `line_sku_activities`, FK `line_sku_activity_id`.
- Los cupos actuales se **copian** a cada `LineSku` de su línea.
- Las posiciones actuales se **borran**; pasan a `line_sku_id` y pierden `line_id`.
- La migración **vacía toda la data de operación** (planes, tareas, sus hijas, borradores, transacciones de material, conversaciones del asistente). Solo queda el catálogo.
- Los seeders se actualizan en otra spec.

Flujo después de esta spec:

1. El usuario asigna actividades con cupo a un `LineSku`: `POST /performances/{id}/activities`.
2. Crea posiciones de ese `LineSku` (CRUD o Excel con columna `sku`). La actividad de la posición debe estar asignada al `LineSku`.
3. Al confirmar empleados en una tarea, posiciones y cupos se buscan en el `LineSku` de la tarea.
4. `GET /weekly-plan-tasks/performances/{id}/lastEmployeesConfiguration` devuelve el personal de la última tarea finalizada de ese `LineSku`.

---

## Alcance

**Dentro:**

- Migración `move_line_activities_and_positions_to_line_skus`:
  - Vacía las tablas de operación (lista en el modelo de datos).
  - Crea `line_sku_activities` y copia cada `line_activities` a todos los `LineSku` de su línea.
  - Cambia `weekly_plan_task_employees.line_activity_id` por `line_sku_activity_id`.
  - Elimina `line_activities`.
  - Borra todas las `positions`, elimina `line_id` y el `unique(code)`, agrega `line_sku_id` y `unique(line_sku_id, code)`.
- Modelo `LineSkuActivity` en lugar de `LineActivity` (se elimina `app/Models/LineActivity.php`).
- Relaciones: `LineSku::lineSkuActivities()`, `LineSku::positions()`, `Position::lineSku()`, `Activity::lineSkuActivities()`, `WeeklyPlanTaskEmployee::lineSkuActivity()`, `LineSkuActivity::taskEmployees()`. Se eliminan `Line::lineActivities()`, `Line::positions()`, `Position::line()`, `Activity::lineActivities()`, `WeeklyPlanTaskEmployee::lineActivity()`.
- Feature `LineActivities` renombrada a `LineSkuActivities` (Interface, Service, Provider, Requests, Resource, Controller).
- Rutas nuevas `GET|POST /performances/{id}/activities` y `PATCH|DELETE /line-sku-activities/{id}` en `routes/skus.php`. Se eliminan las 4 rutas de actividades de `routes/lines.php`.
- `Positions`: `line_sku_id` en lugar de `line_id` en Requests, Service, Resource, filtros, Excel (`uploadFile` con columna `sku`) y `PositionFactory`.
- `Activities`: filtro `?line_sku_id=` en lugar de `?line_id=`; regla de borrado contra `line_sku_activities`.
- `WeeklyPlanTaskEmployees`: `confirmEmployees`, `confirmEmployeesByPosition`, `updateEmployeeActivity` y `getLastEmployeesConfiguration` trabajan contra el `LineSku` de la tarea. Campo `line_activity_id` renombrado a `line_sku_activity_id` en bodies y respuestas.
- Ruta `GET /weekly-plan-tasks/performances/{id}/lastEmployeesConfiguration` en lugar de `/weekly-plan-tasks/lines/{code}/lastEmployeesConfiguration`.
- `LineResource` pierde `activities` y `positions`; `LineSkuResource` los gana (calculados sobre el `LineSku`).
- Eager loading en `WeeklyPlanTaskAssistantService::getTaskEmployees`.
- Documentación: `public/openapi.yaml`, `references/line-activities.md`, `references/list-filters.md`, `references/weekly-plan-task-employees.md`, `references/weekly-plan-task-employees-updated.md`, `references/base-data-excel-bulk-upload.md`.

**Fuera de alcance (para specs futuras):**

- Actualizar seeders y sus JSON (`CatalogSeeder`, `positions.json`, `line_activities.json`, `OperationSeeder`, `WeeklyPlanTaskHistorySeeder`, `WeeklyPlanTaskAssistantDemoSeeder`). Quedan rotos tras esta spec.
- Copiar la configuración (cupos o posiciones) de un `LineSku` a otro.
- Carga Excel de cupos.
- Cupos por defecto en la línea con override por `LineSku`.
- Volver `positions.activity_id` o `weekly_plan_task_employees.line_sku_activity_id` NOT NULL.
- Error por empleado cuando la actividad enviada en `confirmEmployees` es de otro `LineSku` (hoy no existe para líneas; se mantiene igual).
- Cambiar `GET /weekly-plan-tasks/{id}/availableEmployees`: el biométrico sigue consultándose por código de línea.
- Restaurar datos en el `down()` de la migración.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `move_line_activities_and_positions_to_line_skus`

`up()`, en este orden:

**1. Vaciar operación.** Con `Schema::disableForeignKeyConstraints()` / `enableForeignKeyConstraints()`, `DB::table($table)->truncate()` sobre:

```text
weekly_plan_task_logs
weekly_plan_task_observations
weekly_plan_task_employees
weekly_plan_task_performance_records
weekly_plan_task_lot_records
weekly_plan_task_product_records
weekly_plan_task_timeouts
packing_material_transaction_items
packing_material_transactions
weekly_plan_tasks
weekly_plans
draft_weekly_plan_tasks
draft_weekly_plans
assistant_audio_summaries
agent_conversation_messages
agent_conversations
```

Los nombres de las tablas del asistente salen de la migración `create_agent_conversations_table` (`$conversationsTable`, `$messagesTable`); se confirman con `database-schema` antes de escribir la migración.

**2. Crear `line_sku_activities`.**

```php
Schema::create('line_sku_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('line_sku_id')->constrained('line_stock_keeping_units')->cascadeOnDelete();
    $table->foreignId('activity_id')->constrained();
    $table->unsignedInteger('quantity');
    $table->timestamps();

    $table->unique(['line_sku_id', 'activity_id']);
});
```

**3. Copiar cupos.** Un `INSERT … SELECT` que, por cada `line_activities` y cada `line_stock_keeping_units` con el mismo `line_id`, crea una fila con `line_sku_id`, `activity_id`, `quantity` y `now()` en los timestamps. Una línea sin `LineSku` pierde sus cupos.

**4. Reapuntar asignaciones.** La tabla ya está vacía.

```php
Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
    $table->dropConstrainedForeignId('line_activity_id');
    $table->foreignId('line_sku_activity_id')->nullable()->after('position_id')->constrained('line_sku_activities');
});
```

**5. Eliminar `line_activities`.** `Schema::drop('line_activities')`.

**6. Posiciones.**

```php
DB::table('positions')->delete();

Schema::table('positions', function (Blueprint $table) {
    $table->dropUnique(['code']);
    $table->dropConstrainedForeignId('line_id');
    $table->foreignId('line_sku_id')->after('code')->constrained('line_stock_keeping_units')->cascadeOnDelete();
    $table->unique(['line_sku_id', 'code']);
});
```

`down()`: revierte solo el esquema, con tablas vacías. Recrea `line_activities` (definición de SPEC 14), restaura `line_activity_id` en `weekly_plan_task_employees`, devuelve `positions.line_id` (nullable) y `unique(code)`, elimina `line_sku_id` y `line_sku_activities`. No restaura datos.

| Tabla / columna | Significado |
| --------------- | ----------- |
| `line_sku_activities.line_sku_id` | `LineSku` al que pertenece el cupo. Cascade: borrar el `LineSku` borra sus cupos. |
| `line_sku_activities.activity_id` | Actividad del catálogo. Sin cascade (SPEC 14). |
| `line_sku_activities.quantity` | Personas de esa actividad que necesita el `LineSku`. Entero ≥ 1. |
| `weekly_plan_task_employees.line_sku_activity_id` | Cupo que cubre el empleado. Nullable como en SPEC 16. |
| `positions.line_sku_id` | `LineSku` del puesto. NOT NULL. Cascade. |
| `unique(positions.line_sku_id, positions.code)` | El mismo código del biométrico se repite entre SKUs de la línea, nunca dentro del mismo. |

### Modelos

`App\Models\LineSkuActivity` (reemplaza `LineActivity`, tabla `line_sku_activities`):

- Fillable: `line_sku_id`, `activity_id`, `quantity`.
- Casts: `quantity` → `integer`.
- Relaciones: `lineSku()` → `belongsTo(LineSku::class, 'line_sku_id')`, `activity()` → `Activity`, `taskEmployees()` → `hasMany(WeeklyPlanTaskEmployee::class)`.

`App\Models\LineSku`:

- Relaciones nuevas: `lineSkuActivities()` → `hasMany(LineSkuActivity::class, 'line_sku_id')`, `positions()` → `hasMany(Position::class, 'line_sku_id')`.

`App\Models\Position`:

- Fillable: `code`, `activity_id`, `line_sku_id`, `status`.
- `lineSku()` → `belongsTo(LineSku::class, 'line_sku_id')` reemplaza `line()`.

`App\Models\Activity`: `lineSkuActivities()` reemplaza `lineActivities()`. `positions()` sin cambios.

`App\Models\Line`: se eliminan `lineActivities()` y `positions()`.

`App\Models\WeeklyPlanTaskEmployee`: Fillable con `line_sku_activity_id` en lugar de `line_activity_id`; `lineSkuActivity()` reemplaza `lineActivity()`.

`Database\Factories\PositionFactory`: `line_sku_id` en lugar de `line_id`.

### Feature `LineSkuActivities`

Renombre de `LineActivities`:

| Antes | Después |
| ----- | ------- |
| `app/Interfaces/LineActivities/LineActivitiesServiceInterface.php` | `app/Interfaces/LineSkuActivities/LineSkuActivitiesServiceInterface.php` |
| `app/Services/LineActivities/LineActivitiesService.php` | `app/Services/LineSkuActivities/LineSkuActivitiesService.php` |
| `app/Providers/LineActivities/LineActivitiesProvider.php` | `app/Providers/LineSkuActivities/LineSkuActivitiesProvider.php` (actualizar `bootstrap/providers.php`) |
| `app/Http/Requests/LineActivities/{Create,Update}LineActivityRequest.php` | `app/Http/Requests/LineSkuActivities/{Create,Update}LineSkuActivityRequest.php` |
| `app/Http/Resources/LineActivities/LineActivityResource.php` | `app/Http/Resources/LineSkuActivities/LineSkuActivityResource.php` |
| `app/Http/Controllers/LineActivitiesController.php` | `app/Http/Controllers/LineSkuActivitiesController.php` |

Métodos de la Interface: `getLineSkuActivities(string $lineSkuId)`, `createLineSkuActivity(string $lineSkuId, array $data)`, `getLineSkuActivityById(string $id)`, `updateLineSkuActivityById(string $id, array $data)`, `deleteLineSkuActivityById(string $id)`.

Endpoints (todos bajo `jwt.auth`, en `routes/skus.php`, antes de `Route::apiResource('/performances', …)`):

| Método   | Ruta | Body | Respuesta |
| -------- | ---- | ---- | --------- |
| `GET`    | `/performances/{id}/activities` | — | 200, `LineSkuActivityResource::collection`, orden por `activities.name` |
| `POST`   | `/performances/{id}/activities` | `activity_id`, `quantity` | 201, `LineSkuActivityResource` |
| `PATCH`  | `/line-sku-activities/{id}` | `quantity` | 200, `data: null` |
| `DELETE` | `/line-sku-activities/{id}` | — | 200, `data: null` |

Acciones del Controller sin cambio de nombre: `activities`, `assignActivity`, `updateActivity`, `removeActivity`.

Mensajes de éxito: `Actividades del SKU de Línea Obtenidas Correctamente`, `Actividad Asignada Correctamente`, `Actividad del SKU de Línea Actualizada Correctamente`, `Actividad Quitada del SKU de Línea Correctamente`.

Requests: mismas reglas y mensajes que SPEC 14 (`activity_id` required|exists, `quantity` required|min:1).

Reglas del Service (inyecta `LineSkusServiceInterface` y `ActivitiesServiceInterface`):

- `LineSku` inexistente → `NotFoundError('El SKU de línea no existe')` (reutiliza `LineSkusServiceInterface::getLineSkuById`).
- Actividad inexistente → `NotFoundError('La actividad no existe')`.
- Actividad ya asignada al `LineSku` → `BadRequestError('La actividad ya está asignada al SKU de línea')`.
- `getLineSkuActivityById` inexistente → `NotFoundError('La actividad del SKU de línea no existe')`.
- `deleteLineSkuActivityById`:
  - `taskEmployees()->withTrashed()->exists()` → `BadRequestError('No se puede quitar una actividad con personal asignado')`.
  - `Position::where('line_sku_id', …)->where('activity_id', …)->exists()` → `BadRequestError('No se puede quitar una actividad con posiciones asignadas')`.

`LineSkuActivityResource`:

```php
[
    'id' => $this->id,
    'line_sku_id' => $this->line_sku_id,
    'activity_id' => $this->activity_id,
    'activity' => $this->activity->name,
    'unit' => $this->activity->unit,
    'quantity' => $this->quantity,
]
```

### `Activities` (SPEC 14 / 17)

- `GET /activities?line_sku_id=N`: `whereHas('lineSkuActivities', fn ($q) => $q->where('line_sku_id', N))`. Reemplaza `?line_id=`. Sin match → lista vacía (200).
- `deleteActivityById`: si `lineSkuActivities()->exists()` → `BadRequestError('No se puede eliminar una actividad asignada a SKUs de línea')`. La regla de posiciones (SPEC 17) no cambia.

### `Positions` (SPEC 17)

`CreatePositionRequest` / `UpdatePositionRequest` reemplazan `line_id` por:

```text
line_sku_id   integer   required|integer|exists:line_stock_keeping_units,id
code          string    required|max:255|Rule::unique('positions','code')->where('line_sku_id', line_sku_id)   (Update: ->ignore({id}))
```

Mensajes: `El SKU de línea es requerido.`, `El SKU de línea debe ser un número entero.`, `El SKU de línea no existe.`, `El código ya existe en el SKU de línea.`

Regla de `LineSku` en `PositionsService` (reemplaza la regla de línea de SPEC 17): existe `line_sku_activities` con `line_sku_id` y `activity_id` del body. Si no:

`La actividad 'RECORTADOR' no está asignada al SKU 'SKU01' de la línea 'L01'`

`GET /positions`:

- `with(['lineSku.line', 'lineSku.sku', 'activity'])`.
- `lineCode` → `whereHas('lineSku.line', code =)`; `line` → `whereHas('lineSku.line', name LIKE)`.
- Nuevos: `sku` → `whereHas('lineSku.sku', code LIKE)`; `line_sku_id` → igualdad exacta.
- `code` y `activity` sin cambios.

`PositionResource`:

```php
[
    'id' => $this->id,
    'code' => $this->code,
    'activity_id' => $this->activity_id,
    'activity' => $this->activity?->name,
    'unit' => $this->activity?->unit,
    'line_sku_id' => $this->line_sku_id,
    'line_id' => $this->lineSku->line_id,
    'line' => $this->lineSku->line->name,
    'sku' => $this->lineSku->sku->code,
    'status' => $this->status,
]
```

`POST /positions/uploadFile` — encabezados `codigo`, `actividad`, `linea`, `sku`:

1. Consultas fijas antes del recorrido: `Line::pluck('id', 'code')`, `Sku::pluck('id', 'code')`, `LineSku` keyed por `"{line_id}|{sku_id}"`, `Activity::pluck('id', 'name')`, set `"{line_sku_id}|{activity_id}"` de `line_sku_activities`, set `"{line_sku_id}|{code}"` de `positions`.
2. Errores por fila, acumulados como hoy, además de los existentes de `codigo`, `actividad` y `linea`:
   - `Línea N: el campo 'sku' es obligatorio`.
   - `Línea N: el sku con código 'X' no existe`.
   - `Línea N: el sku 'X' no está asignado a la línea 'L'`.
   - `Línea N: el código 'C' ya existe en el sku 'X' de la línea 'L'`.
   - `Línea N: el código 'C' está repetido en el archivo para el sku 'X' de la línea 'L'`.
   - `Línea N: la actividad 'A' no está asignada al sku 'X' de la línea 'L'` (reemplaza el error de línea de SPEC 17).
3. Insert con `line_sku_id` en lugar de `line_id`.

### `WeeklyPlanTaskEmployees` (SPEC 15 / 16)

Se renombra `line_activity_id` → `line_sku_activity_id` en:

- `ConfirmWeeklyPlanTaskEmployeesRequest`: `employees.*.line_sku_activity_id` (mismos mensajes).
- `UpdateWeeklyPlanTaskEmployeeActivityRequest`: `line_sku_activity_id` (mismos mensajes).
- `WeeklyPlanTaskEmployeeResource`: `line_sku_activity_id`; `activity` y `unit` desde `lineSkuActivity.activity`.
- `ConfirmWeeklyPlanTaskEmployeeResource` / `LastEmployeesConfigurationResource`: `line_sku_activity_id`.

El `LineSku` de la tarea es `$task->performance`. La línea (`getTaskLine`) se sigue usando para `is_wildcard` y el biométrico.

`confirmEmployees`:

- Posiciones: `Position::where('line_sku_id', $task->line_sku_id)->whereIn('code', $codes)`.
- Cupos: `LineSkuActivity::with('activity')->whereIn('id', …)`.
- Línea comodín: primera posición de `$task->performance->positions()`. Sin posiciones → `BadRequestError('El SKU de línea debería de tener por lo menos una posición')`.
- Cupo: solo se valida contra los `LineSkuActivity` cuyo `line_sku_id` es el de la tarea (equivalente al filtro actual por `line_id`).
- Resto de reglas y mensajes sin cambios.

`confirmEmployeesByPosition`:

- Posiciones: `Position::with('activity')->where('line_sku_id', $task->line_sku_id)->whereIn('code', $codes)`.
- Cupos: `LineSkuActivity::with('activity')->where('line_sku_id', $task->line_sku_id)->whereIn('activity_id', …)`.
- Mensaje: `La actividad 'X' de la posición 'Y' no está asignada al SKU de línea de la tarea`.

`updateEmployeeActivity`:

- `LineSkuActivitiesServiceInterface::getLineSkuActivityById` (404 `La actividad del SKU de línea no existe`).
- `line_sku_id` distinto al de la tarea → `BadRequestError("La actividad 'X' no pertenece al SKU de línea de la tarea")`.
- Cupo y resto sin cambios.

`getLastEmployeesConfiguration(string $lineSkuId)`:

- Ruta `GET /weekly-plan-tasks/performances/{id}/lastEmployeesConfiguration`. Se elimina `/weekly-plan-tasks/lines/{code}/lastEmployeesConfiguration`.
- `LineSkusServiceInterface::getLineSkuById` (404 `El SKU de línea no existe`).
- Última tarea con `end_date` y `line_sku_id = {id}`. Sin tarea → `NotFoundError('El SKU de línea no tiene tareas finalizadas')`.
- Mensaje de éxito sin cambios.

Eager loading `with(['catalogPosition.activity', 'lineSkuActivity.activity'])` en `WeeklyPlanTaskEmployeesService::getTaskEmployees` y `WeeklyPlanTaskAssistantService::getTaskEmployees`.

### `Lines` y `LineSkus`

- `LineResource`: se quitan `activities` y `positions`.
- `LineSkuResource` agrega `activities` (suma de `quantity` de sus cupos) y `positions` (conteo).
- `LineSkusService::getLineSkus` y `getLineSkuById`: `withSum('lineSkuActivities', 'quantity')` y `withCount('positions')`. El Resource lee `(int) $this->line_sku_activities_sum_quantity` y `$this->positions_count`. Sin N+1.

---

## Plan de implementación

Los pasos 1 a 6 se despliegan juntos: la migración elimina `line_activities` y `positions.line_id`, y el código anterior deja de funcionar hasta el paso 6. Cada paso es commiteable por separado.

1. **Migración.** `php artisan make:migration move_line_activities_and_positions_to_line_skus --no-interaction` con el `up`/`down` del modelo de datos. Confirmar nombres de tablas del asistente con `database-schema` antes. Verificación: `php artisan migrate` sin error; `database-schema` muestra `line_sku_activities`, `positions.line_sku_id`, `weekly_plan_task_employees.line_sku_activity_id` y no `line_activities` ni `positions.line_id`; las tablas de operación tienen 0 filas; `line_sku_activities` tiene una fila por cada par (cupo de línea, `LineSku` de esa línea).
2. **Modelos y factory.** `php artisan make:model LineSkuActivity --no-interaction`; eliminar `LineActivity`. Relaciones nuevas y eliminadas de la sección Modelos. `PositionFactory` con `line_sku_id`. Verificación: `php artisan tinker --execute 'App\Models\LineSku::first()?->lineSkuActivities()->count();'` sin error.
3. **Feature `LineSkuActivities`.** Renombre completo de la tabla de archivos, Provider en `bootstrap/providers.php`, rutas en `routes/skus.php` y eliminación de las 4 rutas de `routes/lines.php`. Verificación: `php artisan route:list --path=performances` y `--path=line-sku-activities` muestran las 4 rutas; `--path=lines` no muestra `activities`.
4. **`Activities`.** Filtro `line_sku_id` y regla de borrado. Verificación: `GET /activities?line_sku_id=N` filtra; borrar una actividad con cupos → 400.
5. **`Positions`.** Requests, regla de `LineSku`, filtros, eager loading, Resource y `uploadFile` con columna `sku`. Verificación: casos de los criterios de aceptación para `/positions`.
6. **`WeeklyPlanTaskEmployees`.** Requests, Resources, los cuatro métodos del Service, ruta de `lastEmployeesConfiguration`, eager loading en `WeeklyPlanTaskAssistantService`. Verificación: casos de los criterios de aceptación para confirmación, `PATCH` y última configuración.
7. **`Lines` y `LineSkus`.** `LineResource` sin `activities`/`positions`; `LineSkuResource` con ambos y `withSum`/`withCount` en el Service. Verificación: `GET /performances` devuelve `activities` y `positions` sin N+1.
8. **Documentación.** `public/openapi.yaml` (rutas nuevas y eliminadas, bodies con `line_sku_id` y `line_sku_activity_id`, schemas de posición, cupo, `LineSku`, `Line` y empleado, filtro `line_sku_id`), `references/line-activities.md` (cupos por `LineSku`), `references/list-filters.md` (`line_sku_id` en actividades; `sku` y `line_sku_id` en posiciones), `references/weekly-plan-task-employees.md` y `references/weekly-plan-task-employees-updated.md` (campo renombrado, ruta nueva), `references/base-data-excel-bulk-upload.md` (columna `sku` y errores).
9. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` deja en 0 filas las 16 tablas de operación listadas.
- [ ] `php artisan migrate` crea `line_sku_activities` con FK cascade a `line_stock_keeping_units`, FK sin cascade a `activities` y `unique(line_sku_id, activity_id)`.
- [ ] Una línea con cupos `RECORTADOR: 10` y `CANASTERO: 2` y 3 `LineSku` queda con 6 filas en `line_sku_activities` (2 por `LineSku`) con las mismas cantidades.
- [ ] La tabla `line_activities` ya no existe.
- [ ] `positions` queda vacía, sin `line_id`, con `line_sku_id` NOT NULL (FK cascade) y `unique(line_sku_id, code)`; `unique(code)` ya no existe.
- [ ] `weekly_plan_task_employees` tiene `line_sku_activity_id` (FK nullable a `line_sku_activities`) y no `line_activity_id`.
- [ ] `php artisan migrate:rollback` termina sin error y restaura el esquema anterior con tablas vacías.
- [ ] `app/Models/LineActivity.php` no existe y `grep -rn "LineActivit\|line_activit\|lineActivit" app routes` no devuelve coincidencias.
- [ ] `GET /lines/{code}/activities` responde 404 de ruta; `GET /performances/{id}/activities` devuelve los cupos de ese `LineSku` ordenados por nombre de actividad.
- [ ] `POST /performances/{id}/activities` con `LineSku` inexistente → 404 `El SKU de línea no existe`.
- [ ] `POST /performances/{id}/activities` con una actividad ya asignada a ese `LineSku` → 400 `La actividad ya está asignada al SKU de línea`; la misma actividad en otro `LineSku` de la misma línea → 201.
- [ ] `PATCH` o `DELETE /line-sku-activities/{id}` inexistente → 404 `La actividad del SKU de línea no existe`.
- [ ] `DELETE /line-sku-activities/{id}` con personal asignado → 400; con posiciones de ese `LineSku` y esa actividad → 400; sin referencias → 200.
- [ ] Borrar un `LineSku` borra sus `line_sku_activities` y sus `positions`.
- [ ] `GET /activities?line_sku_id=N` devuelve solo las actividades asignadas a ese `LineSku`; `?line_sku_id=99999` → 200 con lista vacía.
- [ ] `DELETE /activities/{id}` de una actividad con cupos → 400 `No se puede eliminar una actividad asignada a SKUs de línea`.
- [ ] `POST /positions` sin `line_sku_id` → 422 `El SKU de línea es requerido.`; con un id inexistente → 422 `El SKU de línea no existe.`
- [ ] `POST /positions` con código `1PIQ1A001` en dos `LineSku` distintos → ambos 201; repetido en el mismo `LineSku` → 422 `El código ya existe en el SKU de línea.`
- [ ] `PUT /positions/{id}` con su mismo código y `LineSku` no falla por `unique`.
- [ ] `POST /positions` con una actividad no asignada al `LineSku` → 400 `La actividad 'X' no está asignada al SKU 'S' de la línea 'L'`.
- [ ] `GET /positions` devuelve `line_sku_id`, `line_id`, `line` y `sku`, sin N+1; los filtros `lineCode`, `line`, `sku` y `line_sku_id` funcionan.
- [ ] `POST /positions/uploadFile` con columnas `codigo`, `actividad`, `linea`, `sku` válidas crea las posiciones con el `line_sku_id` correcto.
- [ ] `POST /positions/uploadFile` con un sku no asignado a la línea, un código repetido en el mismo `LineSku` y una actividad no asignada → un único 400 con los tres mensajes por número de línea; no inserta nada.
- [ ] `uploadFile` usa un número fijo de consultas, sin importar cuántas filas lleguen.
- [ ] `POST /weekly-plan-tasks/{id}/confirmEmployees` sin `line_sku_activity_id` → 422 `La actividad del empleado es requerida`.
- [ ] `confirmEmployees` con posiciones y cupos del `LineSku` de la tarea, dentro del cupo → 201 y filas con `line_sku_activity_id`.
- [ ] `confirmEmployees` con un código de posición que solo existe en otro `LineSku` → 400 `La posición 'X' no existe`.
- [ ] `confirmEmployees` excediendo el cupo del `LineSku` → 400 con un mensaje por actividad.
- [ ] `confirmEmployees` en una línea comodín cuyo `LineSku` no tiene posiciones → 400 `El SKU de línea debería de tener por lo menos una posición`.
- [ ] `confirmEmployeesByPosition` resuelve posición y cupo dentro del `LineSku` de la tarea; una actividad de posición sin cupo en el `LineSku` → 400 `La actividad 'X' de la posición 'Y' no está asignada al SKU de línea de la tarea`.
- [ ] `PATCH /weekly-plan-task-employees/{id}` con `line_sku_activity_id` de otro `LineSku` → 400 `La actividad 'X' no pertenece al SKU de línea de la tarea`; del mismo con cupo → 200.
- [ ] `GET /weekly-plan-tasks/performances/{id}/lastEmployeesConfiguration` devuelve el personal de la última tarea finalizada de ese `LineSku` con `line_sku_activity_id`; sin tareas finalizadas → 404 `El SKU de línea no tiene tareas finalizadas`.
- [ ] `GET /weekly-plan-tasks/lines/{code}/lastEmployeesConfiguration` ya no existe.
- [ ] `GET /weekly-plan-tasks/{id}/employees` y la tool de IA `GetWeeklyPlanTaskEmployees` devuelven `line_sku_activity_id`, `activity` y `unit`, sin N+1.
- [ ] `GET /lines` no devuelve `activities` ni `positions`; `GET /performances` y `GET /performances/{id}` sí, calculados sobre el `LineSku` y sin N+1.
- [ ] `public/openapi.yaml` y los 5 archivos de `references/` listados reflejan el cambio.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** configuración (cupos y posiciones) por `LineSku`. Pedido del usuario: cada SKU de una línea tiene su propia dotación.
- **Sí:** reemplazar `line_id` por `line_sku_id`, una sola fuente de verdad.
- **No:** cupos por defecto en la línea con override por `LineSku`. Dos fuentes de verdad y lógica de resolución.
- **Sí:** copiar cada cupo actual a todos los `LineSku` de su línea. Elegido por el usuario: no se pierde la configuración cargada.
- **Sí:** renombrar a `LineSkuActivity` / `line_sku_activities` / `line_sku_activity_id` / `/line-sku-activities/{id}`. Elegido por el usuario: el nombre refleja a quién pertenece el cupo. Rompe contrato con el frontend.
- **No:** mantener `LineActivity` con otra columna. Descartado por el usuario.
- **Sí:** rutas `GET|POST /performances/{id}/activities`. `LineSku` ya se expone como `/performances`; un solo lookup por id.
- **No:** `/lines/{code}/skus/{sku}/activities`. Dos lookups y rutas largas.
- **Sí:** posiciones con `line_sku_id` y sin `line_id`. Elegido por el usuario; la línea se deriva de `lineSku.line`.
- **Sí:** `unique(line_sku_id, code)`. Elegido por el usuario: el mismo puesto físico existe en varios SKUs de la línea.
- **Sí:** borrar todas las posiciones en la migración. Elegido por el usuario; se recargan por CRUD o Excel.
- **Sí:** la migración vacía toda la data de operación, incluidas conversaciones y audios del asistente. Elegido por el usuario: solo queda el catálogo. Evita reapuntar FKs de asignaciones históricas.
- **No:** paso manual previo o `migrate:fresh`. Descartados por el usuario.
- **Sí:** `down()` solo revierte el esquema. Los datos truncados no se pueden recuperar, y devolver cupos por `LineSku` a uno por línea sería ambiguo.
- **Sí:** cascade desde `line_stock_keeping_units` en cupos y posiciones. Mismo criterio que SPEC 14 con `lines`.
- **Sí:** la actividad de una posición debe estar asignada a su `LineSku`. Traslada la regla de SPEC 17.
- **Sí:** filtro `?line_sku_id=` en `/activities` en lugar de `?line_id=`. Elegido por el usuario: coincide con la nueva regla de posiciones.
- **Sí:** Excel de posiciones con columna `sku` y `LineSku` resuelto por (`linea`, `sku`). Elegido por el usuario: amigable para quien arma el archivo.
- **No:** columna `line_sku_id` en el Excel.
- **Sí:** `lastEmployeesConfiguration` por `LineSku`. Elegido por el usuario: devuelve posiciones y cupos válidos para reconfirmar.
- **Sí:** mantener sin error por empleado la actividad de otro `LineSku` en `confirmEmployees`. Paridad con el código actual, que lo quitó al soportar líneas comodín (commit `d04bfc5`).
- **Sí:** `activities` y `positions` pasan de `LineResource` a `LineSkuResource`. Elegido por el usuario. `withSum`/`withCount` evitan N+1.
- **Sí:** `line_sku_activity_id` sigue nullable. Paridad con SPEC 16; volverlo NOT NULL queda fuera.
- **No:** copiar configuración entre `LineSku` ni carga Excel de cupos. Elegido por el usuario: solo CRUD.
- **No:** actualizar seeders. Elegido por el usuario: otra spec.
- **No:** tests automatizados. Consistente con specs anteriores.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| La migración borra toda la operación en cualquier entorno donde se ejecute, incluido producción. | Aceptado por el usuario. Respaldo de BD antes de `php artisan migrate` en producción. |
| `CatalogSeeder`, `OperationSeeder`, `WeeklyPlanTaskHistorySeeder` y `WeeklyPlanTaskAssistantDemoSeeder` fallan tras la migración (`line_activities` y `positions.line_id` no existen). | Aceptado; no ejecutar `db:seed` hasta la spec de seeders. |
| Una línea sin `LineSku` pierde sus cupos al copiar. | Aceptado: sin `LineSku` no hay tareas que los usen. |
| Bodies y respuestas cambian (`line_sku_activity_id`, `line_sku_id`, rutas nuevas) y el frontend actual recibe 404 o 422. | Desplegar junto con el cambio del frontend. |
| Cargar posiciones por cada `LineSku` multiplica el trabajo (mismo puesto repetido en cada SKU). | Excel con columna `sku` permite cargarlas en bloque. Copiar entre `LineSku` va en otra spec. |
| `truncate` con FKs deshabilitadas deja la BD inconsistente si la migración falla a mitad. | Restaurar el respaldo y reintentar. |

---

## Lo que **no** está en esta spec

- Actualización de seeders y sus JSON.
- Copia de configuración entre `LineSku`.
- Carga Excel de cupos.
- Cupos por defecto en la línea con override.
- `positions.activity_id` o `line_sku_activity_id` NOT NULL.
- Error por empleado con actividad de otro `LineSku` en `confirmEmployees`.
- Cambios en `availableEmployees` (biométrico por línea).
- Restauración de datos en el `down()`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
