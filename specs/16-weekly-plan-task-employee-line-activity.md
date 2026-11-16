# SPEC 16 — Actividad de la línea para el personal asignado a una tarea

> **Estado:** Aprobado
> **Depende de:** SPEC 14, SPEC 15
> **Fecha:** 2026-11-16
> **Objetivo:** Al confirmar empleados en una `WeeklyPlanTask`, el usuario elige para cada uno la `LineActivity` de la línea de la tarea que va a cubrir; el backend la guarda en `weekly_plan_task_employees.line_activity_id` respetando el cupo máximo (`quantity`).

---

## Por qué existe esta spec

SPEC 14 dejó configurado, por línea, cuántas personas necesita cada actividad (`line_activities.quantity`). SPEC 15 vinculó cada empleado de la tarea con su `positions` (código del biométrico). Falta unir las dos cosas: hoy no se sabe qué actividad del catálogo cubre cada empleado en la tarea ni se controla que no se exceda el cupo.

`positions.activity` es texto libre y no sirve para eso. Esta spec agrega una FK `line_activity_id` en `weekly_plan_task_employees`, la pide en la confirmación y permite corregirla después.

Flujo:

1. El frontend pide `GET /weekly-plan-tasks/{id}/availableEmployees` (biométrico) y `GET /lines/{code}/activities` (cupos de la línea de la tarea).
2. El usuario elige, por empleado, una actividad de la línea.
3. `POST /weekly-plan-tasks/{id}/confirmEmployees` con `employees[].line_activity_id`.
4. El backend valida posición (SPEC 15), que la `LineActivity` exista y sea de la línea de la tarea, y que el total por actividad (ya asignados + enviados) no supere `quantity`. Cualquier error → un solo 400 con todos los mensajes; no se inserta nada.
5. Con la tarea en status `3` o `4`, `PATCH /weekly-plan-task-employees/{id}` cambia la actividad de una asignación con las mismas validaciones.
6. `GET /weekly-plan-tasks/{id}/employees` devuelve `line_activity_id`, `activity` (nombre del catálogo) y `unit`; el texto de `positions.activity` pasa a `position_activity`.

---

## Alcance

**Dentro:**

- Migración: `weekly_plan_task_employees.line_activity_id` (FK nullable a `line_activities`, sin cascade). Sin backfill.
- Relaciones `WeeklyPlanTaskEmployee::lineActivity()` y `LineActivity::taskEmployees()`; `line_activity_id` en el Fillable.
- `ConfirmWeeklyPlanTaskEmployeesRequest`: `employees.*.line_activity_id` obligatorio.
- `WeeklyPlanTaskEmployeesService::confirmEmployees`: validar existencia, línea y cupo; guardar `line_activity_id`.
- Endpoint nuevo `PATCH /weekly-plan-task-employees/{id}` para cambiar `line_activity_id` de una asignación (status `3` o `4`).
- `LineActivitiesService::deleteLineActivityById`: 400 si la `LineActivity` está referenciada por alguna asignación (incluye soft delete).
- `WeeklyPlanTaskEmployeeResource`: `activity` pasa a ser `activities.name`; `positions.activity` se mueve a `position_activity`; se agregan `line_activity_id` y `unit`.
- Eager loading de `lineActivity.activity` en `WeeklyPlanTaskEmployeesService::getTaskEmployees` y `WeeklyPlanTaskAssistantService::getTaskEmployees`.
- Documentación: `public/openapi.yaml`, `references/weekly-plan-task-employees.md` y `references/line-activities.md`.

**Fuera de alcance (para specs futuras):**

- Backfill de asignaciones existentes. Quedan con `line_activity_id = null`; se corrigen con el `PATCH`.
- Aceptar `LineActivity` de líneas dependientes (`LineDependencies`).
- Validar que `quantity` no baje por debajo de las personas ya asignadas al editar el cupo (`PATCH /line-activities/{id}`). Se permite.
- Cambiar `position` o cualquier otro campo de una asignación por `PATCH`.
- Validar coherencia entre `positions.activity` y la actividad elegida.
- Validar status de la tarea (`2`) o personal ya asignado en `confirmEmployees` (los helpers existen y siguen sin invocarse).
- Agregar empleados individualmente después de confirmar.
- Proteger el borrado de una línea cuyas `line_activities` están referenciadas (ver Riesgos).
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `add_line_activity_id_to_weekly_plan_task_employees_table`

```php
public function up(): void
{
    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->foreignId('line_activity_id')->nullable()->after('position_id')->constrained();
    });
}

public function down(): void
{
    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->dropConstrainedForeignId('line_activity_id');
    });
}
```

| Columna            | Significado |
| ------------------ | ----------- |
| `line_activity_id` | Actividad de la línea que cubre el empleado en esta tarea. FK a `line_activities` sin cascade. Nullable solo por asignaciones anteriores a esta spec; toda asignación nueva lo lleva. |

### Modelos

`App\Models\WeeklyPlanTaskEmployee`:

- Fillable: `weekly_plan_task_id`, `code`, `name`, `position_id`, `line_activity_id`.
- Relación nueva: `lineActivity()` → `belongsTo(LineActivity::class)`.

`App\Models\LineActivity`:

- Relación nueva: `taskEmployees()` → `hasMany(WeeklyPlanTaskEmployee::class)`.

### Línea de la tarea

Se obtiene como hoy en `getAvailableEmployees`: `$task->performance?->line`. Si es `null` → `BadRequestError('La tarea no tiene una línea asignada')` tanto en `confirmEmployees` como en el `PATCH`.

### Endpoint `POST /weekly-plan-tasks/{id}/confirmEmployees`

Body:

```json
{
  "employees": [
    { "code": "E0001", "name": "Juan Pérez", "position": "1PIQ1A001", "line_activity_id": 4 },
    { "code": "E0002", "name": "María López", "position": "1PIQ1A002", "line_activity_id": 4 },
    { "code": "E0003", "name": "Pedro Gómez", "position": "1PIQ1A011", "line_activity_id": 5 }
  ]
}
```

`ConfirmWeeklyPlanTaskEmployeesRequest` agrega:

```text
employees.*.line_activity_id   integer   required|integer
```

Mensajes: `La actividad del empleado es requerida`, `La actividad del empleado debe ser un número entero`. Sin `distinct` (varios empleados cubren la misma actividad) y sin `exists:` (la existencia se valida en el Service, con el 400 acumulado).

Reglas en `WeeklyPlanTaskEmployeesService::confirmEmployees`, en este orden:

1. Tarea inexistente → `NotFoundError` (reutiliza `getWeeklyPlanTaskById`).
2. Tarea sin línea → `BadRequestError('La tarea no tiene una línea asignada')`.
3. Una consulta para posiciones (como en SPEC 15) y una para actividades: `LineActivity::with('activity')->whereIn('id', $lineActivityIds)->get()->keyBy('id')`.
4. Una consulta para lo ya asignado: asignaciones **activas** (sin soft delete) de la tarea agrupadas por `line_activity_id` con su conteo.
5. Por cada empleado se acumulan errores:
   - Posición inexistente → `La posición 'X' no existe` (SPEC 15).
   - `LineActivity` inexistente → `La actividad de la línea '7' no existe`.
   - `LineActivity` de otra línea → `La actividad 'RECORTADOR' no pertenece a la línea de la tarea`.
6. Por cada `LineActivity` válida y de la línea: si `ya asignados + enviados > quantity` → `La actividad 'RECORTADOR' admite 10 personas; ya hay 3 asignadas y se enviaron 8`. Un mensaje por actividad, no por empleado.
7. Si hay errores → un único `BadRequestError` con los mensajes unidos por `PHP_EOL`. No se inserta nada ni cambia el status.
8. Sin errores, dentro de `DB::transaction`: `insert` con `weekly_plan_task_id`, `code`, `name`, `position_id`, `line_activity_id`, `created_at`, `updated_at`; luego `status = 3` y `save()`.

Respuesta sin cambios: 201, `data: null`, `Personal Asignado Correctamente`.

### Endpoint nuevo `PATCH /weekly-plan-task-employees/{id}`

En `routes/weeklyplantasks.php`, grupo `// FUNCTIONALITYS`, junto al `DELETE` existente. Acción `updateEmployeeActivity` en `WeeklyPlanTaskEmployeesController`; método `updateEmployeeActivity(string $assignmentId, array $data)` en la Interface y el Service.

`{id}` es el id de la **asignación**.

Body:

```json
{ "line_activity_id": 5 }
```

`UpdateWeeklyPlanTaskEmployeeActivityRequest` (nuevo, en `app/Http/Requests/WeeklyPlanTaskEmployees/`):

```text
line_activity_id   integer   required|integer
```

Mensajes: `La actividad es requerida`, `La actividad debe ser un número entero`.

`WeeklyPlanTaskEmployeesService` inyecta además `LineActivitiesServiceInterface`. Reglas:

1. Asignación inexistente o con soft delete → `NotFoundError('La asignación no existe')` (reutiliza `getAssignmentById`).
2. Tarea con status distinto de `3` o `4` → 400 (reutiliza `ensureTaskAcceptsEmployeeChanges`).
3. Tarea sin línea → `BadRequestError('La tarea no tiene una línea asignada')`.
4. `LineActivity` inexistente → `NotFoundError('La actividad de la línea no existe')` (reutiliza `LineActivitiesServiceInterface::getLineActivityById`).
5. `LineActivity` de otra línea → `BadRequestError("La actividad 'RECORTADOR' no pertenece a la línea de la tarea")`.
6. Asignaciones activas de la tarea con ese `line_activity_id`, **excluyendo esta**, `+ 1 > quantity` → `BadRequestError("La actividad 'RECORTADOR' admite 10 personas y ya tiene 10 asignadas")`.
7. Actualiza `line_activity_id`.

Respuesta: 200, `data: null`, `Actividad del Empleado Actualizada Correctamente`.

Enviar el mismo `line_activity_id` que ya tiene es válido (la regla 6 excluye la propia asignación). Sirve también para asignar actividad a filas históricas con `null`.

### Endpoint `DELETE /line-activities/{id}` (SPEC 14)

`LineActivitiesService::deleteLineActivityById`: si `$lineActivity->taskEmployees()->withTrashed()->exists()` → `BadRequestError('No se puede quitar una actividad con personal asignado')`. Se incluye `withTrashed()` porque las filas con soft delete siguen en la tabla y la FK las cuenta.

### Endpoint `GET /weekly-plan-tasks/{id}/employees`

`WeeklyPlanTaskEmployeeResource`:

```php
[
    'id' => $this->id,
    'name' => $this->name,
    'code' => $this->code,
    'position_id' => $this->position_id,
    'position' => $this->position?->code,
    'position_activity' => $this->position?->activity,
    'line_activity_id' => $this->line_activity_id,
    'activity' => $this->lineActivity?->activity?->name,
    'unit' => $this->lineActivity?->activity?->unit,
]
```

- `activity` cambia de significado: antes `positions.activity`, ahora `activities.name`. El valor anterior queda en `position_activity`.
- Asignaciones históricas: `line_activity_id`, `activity` y `unit` son `null`.

Ambos `getTaskEmployees` cargan `with(['position', 'lineActivity.activity'])`. La tool de IA `GetWeeklyPlanTaskEmployees` usa el mismo Resource y recibe los campos nuevos sin cambios propios.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración y modelos.** `php artisan make:migration add_line_activity_id_to_weekly_plan_task_employees_table --no-interaction` con el `up`/`down` del modelo de datos. Fillable y `lineActivity()` en `WeeklyPlanTaskEmployee`; `taskEmployees()` en `LineActivity`. Verificación: `php artisan migrate` sin error; `database-schema` muestra `line_activity_id` nullable con FK a `line_activities`.
2. **Borrado protegido de `LineActivity`.** Regla en `LineActivitiesService::deleteLineActivityById`. Verificación: con una asignación apuntando a la `LineActivity` → 400; sin asignaciones → 200.
3. **Confirmación.** `employees.*.line_activity_id` en `ConfirmWeeklyPlanTaskEmployeesRequest`; validaciones de línea, existencia y cupo en `confirmEmployees`; `line_activity_id` en el insert. Verificación: casos de los criterios de aceptación para `confirmEmployees`.
4. **Edición de actividad.** `UpdateWeeklyPlanTaskEmployeeActivityRequest`, `updateEmployeeActivity` en Interface, Service y Controller, inyección de `LineActivitiesServiceInterface`, ruta `PATCH /weekly-plan-task-employees/{id}`. Verificación: `php artisan route:list --path=weekly-plan-task-employees` muestra `PATCH` y `DELETE`; casos de los criterios de aceptación para el `PATCH`.
5. **Lecturas.** Eager loading `lineActivity.activity` en ambos `getTaskEmployees`; Resource con `position_activity`, `line_activity_id`, `activity`, `unit`. Verificación: `GET /weekly-plan-tasks/{id}/employees` devuelve los campos sin N+1.
6. **Documentación.** `public/openapi.yaml` (body y 400 de `confirmEmployees`, endpoint `PATCH`, 400 nuevo de `DELETE /line-activities/{id}`, schema `WeeklyPlanTaskEmployee`), `references/weekly-plan-task-employees.md` (tipos, flujo, errores, nuevo endpoint, cambio de `activity`) y `references/line-activities.md` (nuevo 400 al quitar).
7. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` agrega `weekly_plan_task_employees.line_activity_id` (FK nullable a `line_activities`, sin cascade); las filas existentes quedan en `null`.
- [ ] `php artisan migrate:rollback` elimina la columna y su FK.
- [ ] `POST …/confirmEmployees` sin `line_activity_id` en algún empleado → 422 `La actividad del empleado es requerida`.
- [ ] `POST …/confirmEmployees` en una tarea sin línea → 400 `La tarea no tiene una línea asignada`.
- [ ] `POST …/confirmEmployees` con posiciones y actividades válidas de la línea y dentro del cupo → 201, filas con `line_activity_id` correcto y la tarea en status `3`.
- [ ] `POST …/confirmEmployees` con un `line_activity_id` inexistente → 400 `La actividad de la línea 'N' no existe`; no inserta nada.
- [ ] `POST …/confirmEmployees` con una `LineActivity` de otra línea → 400 `La actividad 'X' no pertenece a la línea de la tarea`; no inserta nada.
- [ ] Con `RECORTADOR` `quantity: 2` y sin asignados, enviar 3 empleados a esa actividad → 400 con un solo mensaje para `RECORTADOR`; enviar 2 → 201.
- [ ] Con `RECORTADOR` `quantity: 2` y 1 asignación activa en la tarea, enviar 2 más → 400; si esa asignación fue retirada (soft delete), enviar 2 → 201.
- [ ] Un body con posición inexistente, actividad de otra línea y cupo excedido devuelve un único 400 con los tres mensajes.
- [ ] `confirmEmployees` resuelve posiciones, actividades y conteo de asignados con un número fijo de consultas, sin importar cuántos empleados lleguen.
- [ ] `PATCH /weekly-plan-task-employees/{id}` con status `3` o `4` y una actividad de la línea con cupo → 200 `Actividad del Empleado Actualizada Correctamente` y la fila cambia.
- [ ] `PATCH` con la tarea en status distinto de `3` o `4` → 400.
- [ ] `PATCH` con asignación inexistente o retirada → 404 `La asignación no existe`.
- [ ] `PATCH` con `line_activity_id` inexistente → 404 `La actividad de la línea no existe`.
- [ ] `PATCH` con actividad de otra línea → 400.
- [ ] `PATCH` a una actividad que ya tiene `quantity` asignaciones activas → 400 `La actividad 'X' admite N personas y ya tiene N asignadas`.
- [ ] `PATCH` con el mismo `line_activity_id` que ya tiene, en una actividad llena, → 200.
- [ ] `PATCH` sobre una asignación histórica con `line_activity_id = null` le asigna la actividad.
- [ ] `DELETE /line-activities/{id}` referenciada por una asignación (activa o retirada) → 400 `No se puede quitar una actividad con personal asignado`; sin referencias → 200.
- [ ] `PATCH /line-activities/{id}` puede bajar `quantity` por debajo de las asignaciones existentes sin error.
- [ ] `GET /weekly-plan-tasks/{id}/employees` devuelve `id`, `name`, `code`, `position_id`, `position`, `position_activity`, `line_activity_id`, `activity` (nombre del catálogo) y `unit`, sin N+1.
- [ ] La tool de IA `GetWeeklyPlanTaskEmployees` devuelve los mismos campos sin error.
- [ ] `public/openapi.yaml`, `references/weekly-plan-task-employees.md` y `references/line-activities.md` reflejan el cambio.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** FK `line_activity_id` a `line_activities`. Un solo id resuelve actividad, línea y cupo.
- **No:** `activity_id` al catálogo. Habría que buscar el cupo en `line_activities` por separado para validar.
- **Sí:** `line_activity_id` obligatorio en la confirmación. Toda asignación nueva queda con actividad.
- **Sí:** columna nullable sin backfill. `positions.activity` es texto libre y no mapea de forma confiable al catálogo; las filas viejas se corrigen con el `PATCH`.
- **No:** backfill por nombre (`positions.activity` = `activities.name`). Coincidencias parciales o faltantes darían datos engañosos.
- **Sí:** la `LineActivity` debe ser de la línea de la tarea (`performance.line`).
- **No:** aceptar líneas dependientes. Se deja para otra spec si se necesita.
- **Sí:** tarea sin línea → 400. Sin línea no hay cupos contra los cuales validar.
- **Sí:** el cupo es un máximo (decidido en SPEC 14). Menos personas sí se permite.
- **Sí:** el conteo suma asignaciones activas de la tarea + enviadas. `confirmEmployees` no bloquea una segunda llamada, así que contar solo el body permitiría exceder el cupo.
- **Sí:** las asignaciones retiradas (soft delete) liberan su lugar en el cupo.
- **Sí:** un mensaje de cupo por actividad, no por empleado. Más legible.
- **Sí:** validación en el Service con 400 acumulado, sin `exists:` en el FormRequest. Consistente con SPEC 15.
- **Sí:** `PATCH /weekly-plan-task-employees/{id}` solo con `line_activity_id`, en status `3` o `4`. Mismas reglas que el retiro individual.
- **No:** editar `position` en el mismo `PATCH`. Fuera de lo pedido.
- **Sí:** `PATCH` con la misma actividad es válido. La asignación propia se excluye del conteo.
- **Sí:** bloquear con 400 el borrado de una `LineActivity` referenciada, contando filas con soft delete. Conserva el histórico y evita el 500 de la FK.
- **No:** `nullOnDelete`. Las asignaciones perderían la actividad en silencio.
- **Sí:** se permite bajar `quantity` por debajo de lo asignado. El cupo se valida al confirmar o editar, no retroactivamente.
- **Sí:** `activity` en el Resource pasa a ser `activities.name`; `positions.activity` se expone como `position_activity`. Elegido por el usuario: el catálogo es la fuente de verdad de la actividad.
- **Sí:** exponer `line_activity_id` (lo necesita el `PATCH`) y `unit` (ya está cargada con la relación).
- **No:** validar coherencia entre `positions.activity` y la actividad elegida. Ambos textos pueden divergir (riesgo aceptado en SPEC 14).
- **No:** tests automatizados. Consistente con specs anteriores.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| El body de `confirmEmployees` cambia (nuevo campo obligatorio) y el frontend actual recibe 422. | Desplegar junto con el cambio del frontend. |
| `activity` en `GET …/employees` cambia de significado; quien lo lea como `positions.activity` muestra otro dato o `null` en históricos. | Documentado en `references/weekly-plan-task-employees.md`; el valor anterior sigue en `position_activity`. |
| Una línea sin cupos cargados (SPEC 14) no permite confirmar personal. | Cargar cupos por `POST /lines/{code}/activities` antes de desplegar; el 400 indica la actividad que falta. |
| Borrar una línea con `line_activities` referenciadas falla por FK (500), porque el cascade de `line_activities` choca con `weekly_plan_task_employees`. | Aceptado en esta spec; las líneas con tareas no se borran en la operación normal. Protección con 400 en otra spec. |
| Dos confirmaciones o `PATCH` concurrentes sobre la misma tarea pueden pasar la validación de cupo a la vez y excederlo. | Aceptado: la confirmación la hace una sola persona por tarea. Bloqueo pesimista en otra spec si aparece. |

---

## Lo que **no** está en esta spec

- Backfill de actividades en asignaciones históricas.
- Líneas dependientes.
- Validación de `quantity` contra asignaciones existentes al editar el cupo.
- Edición de `position` en una asignación.
- Coherencia `positions.activity` ↔ `activities.name`.
- Validación de status o personal previo en `confirmEmployees`.
- Alta individual de empleados tras confirmar.
- Protección del borrado de líneas con actividades referenciadas.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
