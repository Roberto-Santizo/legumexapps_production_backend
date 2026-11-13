# SPEC 15 — Referencia a `positions` en el personal asignado a una tarea

> **Estado:** Implementado
> **Depende de:** SPEC 06
> **Fecha:** 2026-11-13
> **Objetivo:** Al confirmar empleados en `POST /weekly-plan-tasks/{id}/confirmEmployees`, resolver cada código de `position` contra la tabla `positions` y guardar en `weekly_plan_task_employees` la FK `position_id` en lugar del texto.

---

## Por qué existe esta spec

Hoy `weekly_plan_task_employees.position` es un `string` con el código que manda el frontend (`1REM1001`). No hay vínculo con `positions`, así que no se puede saber la línea ni la actividad (`RECORTADOR`, `CANASTERO`…) del puesto que ocupó cada empleado en la tarea.

Esta spec reemplaza el texto por una FK a `positions`. El contrato del body de `confirmEmployees` no cambia: el frontend sigue enviando el código; el backend lo resuelve.

Flujo:

1. El frontend pide `GET /weekly-plan-tasks/{id}/availableEmployees` (biométrico) y confirma con `employees[].position` = código de posición.
2. El backend busca todas las posiciones por código en una sola consulta.
3. Si algún código no existe → 400 con un mensaje por código; no se inserta nada.
4. Si todos existen → inserta las filas con `position_id` y pasa la tarea a status `3`.
5. `GET /weekly-plan-tasks/{id}/employees` devuelve `position_id`, `position` (código) y `activity`.

---

## Alcance

**Dentro:**

- Migración nueva sobre `weekly_plan_task_employees`: agrega `position_id` (FK nullable a `positions`), hace backfill por código y elimina la columna `position`.
- Relación `WeeklyPlanTaskEmployee::position()` → `belongsTo(Position::class)`; `position_id` en el Fillable en lugar de `position`.
- `WeeklyPlanTaskEmployeesService::confirmEmployees`: resolver códigos a `position_id`, error 400 acumulado si alguno no existe, escritura en `DB::transaction`.
- `WeeklyPlanTaskEmployeeResource`: expone `position_id`, `position` (= `positions.code`) y `activity` (= `positions.activity`).
- Eager loading de `position` en `WeeklyPlanTaskEmployeesService::getTaskEmployees` y `WeeklyPlanTaskAssistantService::getTaskEmployees` (este último alimenta la tool de IA `GetWeeklyPlanTaskEmployees`, que usa el mismo Resource).
- Documentación: `public/openapi.yaml` (endpoint `confirmEmployees` y schema `WeeklyPlanTaskEmployee`) y `references/weekly-plan-task-employees.md`.

**Fuera de alcance (para specs futuras):**

- Vincular el empleado con `LineActivity` / `activities` (SPEC 14) y validar cupos máximos por actividad.
- Validar que la posición pertenezca a la línea de la tarea o a sus líneas dependientes.
- Validar status de la tarea (`2`) o personal ya asignado en `confirmEmployees` (los helpers `ensureTaskAwaitsEmployeeConfirmation` existen pero no se invocan; no se cambia aquí).
- Cambiar el body a `position_id`.
- Validar que la posición tenga `status = 1`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `replace_position_with_position_id_on_weekly_plan_task_employees_table`

```php
public function up(): void
{
    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->foreignId('position_id')->nullable()->after('name')->constrained();
    });

    DB::table('weekly_plan_task_employees')->update([
        'position_id' => DB::raw('(select positions.id from positions where positions.code = weekly_plan_task_employees.position)'),
    ]);

    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->dropColumn('position');
    });
}

public function down(): void
{
    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->string('position')->nullable()->after('name');
    });

    DB::table('weekly_plan_task_employees')->update([
        'position' => DB::raw('(select positions.code from positions where positions.id = weekly_plan_task_employees.position_id)'),
    ]);

    Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
        $table->dropConstrainedForeignId('position_id');
    });
}
```

| Columna       | Significado |
| ------------- | ----------- |
| `position_id` | Posición que ocupó el empleado en esta tarea. FK a `positions` sin cascade. Nullable solo por filas históricas cuyo código no existe en `positions`; toda fila nueva lo lleva. |

- El backfill usa subconsulta correlacionada (compatible con PostgreSQL, el driver actual) e incluye filas con soft delete.
- Filas existentes cuyo código no existe en `positions` quedan con `position_id = null` y su código se pierde al eliminar la columna. Aceptado.
- `positions.code` es único, así que la subconsulta devuelve como máximo una fila.

### Modelo `App\Models\WeeklyPlanTaskEmployee`

- Fillable: `weekly_plan_task_id`, `code`, `name`, `position_id`.
- Relación nueva: `position()` → `belongsTo(Position::class)`.

`positions` no cambia.

### Endpoint `POST /weekly-plan-tasks/{id}/confirmEmployees`

Body sin cambios:

```json
{
  "employees": [
    { "code": "E0001", "name": "Juan Pérez", "position": "1REM1001" },
    { "code": "E0002", "name": "María López", "position": "1REM1002" }
  ]
}
```

`ConfirmWeeklyPlanTaskEmployeesRequest` sin cambios (`employees.*.position` sigue `required|distinct`). La existencia se valida en el Service, no con `exists:` en el request.

Reglas en `WeeklyPlanTaskEmployeesService::confirmEmployees`:

1. Tarea inexistente → `NotFoundError` (reutiliza `getWeeklyPlanTaskById`, como hoy).
2. Una sola consulta: `Position::whereIn('code', $codes)->get()->keyBy('code')`.
3. Por cada empleado cuyo código no esté en el mapa se acumula `La posición 'X' no existe`.
4. Si hay errores → un único `BadRequestError` con los mensajes unidos por `PHP_EOL`. No se inserta nada ni cambia el status.
5. Si no hay errores, dentro de `DB::transaction`: `insert` de las filas con `weekly_plan_task_id`, `code`, `name`, `position_id`, `created_at`, `updated_at`; luego `status = 3` y `save()`.

Respuesta sin cambios: 201, `data: null`, `Personal Asignado Correctamente`.

### Endpoint `GET /weekly-plan-tasks/{id}/employees`

`WeeklyPlanTaskEmployeeResource`:

```php
[
    'id' => $this->id,
    'name' => $this->name,
    'code' => $this->code,
    'position_id' => $this->position_id,
    'position' => $this->position?->code,
    'activity' => $this->position?->activity,
]
```

`position` sigue siendo el código (string), así el frontend actual no se rompe. Para filas históricas sin match, `position_id`, `position` y `activity` son `null`.

`getTaskEmployees` (ambos Services) carga `with('position')` para evitar N+1.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración y modelo.** `php artisan make:migration replace_position_with_position_id_on_weekly_plan_task_employees_table --no-interaction` con el `up`/`down` del modelo de datos. Fillable y relación `position()` en `WeeklyPlanTaskEmployee`. En el mismo paso, `WeeklyPlanTaskEmployeeResource` pasa a leer `position` vía relación (la columna string deja de existir). Verificación: `php artisan migrate` sin error; `database-schema` muestra `position_id` y no `position`; filas previas con código válido tienen `position_id`.
2. **Confirmación.** `confirmEmployees` resuelve códigos, acumula errores y escribe en `DB::transaction`. Verificación: confirmar con códigos válidos inserta `position_id`; con un código inexistente → 400 y no inserta.
3. **Lecturas.** `with('position')` en `WeeklyPlanTaskEmployeesService::getTaskEmployees` y `WeeklyPlanTaskAssistantService::getTaskEmployees`; Resource con `position_id`, `position`, `activity`. Verificación: `GET /weekly-plan-tasks/{id}/employees` devuelve los tres campos sin consultas N+1.
4. **Documentación.** `public/openapi.yaml` (descripción y ejemplo de `confirmEmployees` con códigos de posición reales, respuesta 400, schema `WeeklyPlanTaskEmployee`) y `references/weekly-plan-task-employees.md`.
5. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [X] `php artisan migrate` agrega `weekly_plan_task_employees.position_id` (FK nullable a `positions`) y elimina la columna `position`.
- [X] Tras migrar, una fila previa con `position = '1REM1001'` tiene `position_id` = id de la posición `1REM1001`; una fila con código inexistente queda con `position_id = null`.
- [X] `php artisan migrate:rollback` restaura la columna `position` con el código y elimina `position_id`.
- [X] `POST /weekly-plan-tasks/{id}/confirmEmployees` con todos los códigos existentes → 201, filas con `position_id` correcto y la tarea en status `3`.
- [X] `POST …/confirmEmployees` con dos códigos inexistentes `X1` y `X2` → 400 con `La posición 'X1' no existe` y `La posición 'X2' no existe` en un solo mensaje; no se inserta ninguna fila y el status no cambia.
- [X] `POST …/confirmEmployees` con una posición de otra línea se acepta (no se valida línea).
- [X] La resolución de códigos usa una sola consulta a `positions`, sin importar cuántos empleados lleguen.
- [X] El body de `confirmEmployees` y `ConfirmWeeklyPlanTaskEmployeesRequest` no cambian.
- [X] `GET /weekly-plan-tasks/{id}/employees` devuelve por empleado `id`, `name`, `code`, `position_id`, `position` (código) y `activity`.
- [X] El listado carga las posiciones con eager loading (sin N+1).
- [X] La tool de IA `GetWeeklyPlanTaskEmployees` devuelve los mismos campos sin error.
- [X] `public/openapi.yaml` y `references/weekly-plan-task-employees.md` reflejan el cambio.
- [X] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** reemplazar la columna string por `position_id`. Una sola fuente de verdad; el código y la actividad se leen de `positions`.
- **No:** mantener el string como copia histórica. Elegido por el usuario: si cambia el código de una posición, el histórico mostrará el código nuevo. Se acepta.
- **Sí:** `position_id` nullable con backfill. Las filas existentes sin match quedan en `null` en lugar de bloquear la migración.
- **No:** migración que falla ante filas huérfanas. Obligaría a limpiar datos a mano antes de desplegar.
- **Sí:** FK sin cascade. Una posición referenciada no se puede borrar (error de FK); proteger el borrado con un 400 queda fuera de alcance.
- **Sí:** body igual (`position` = código). El frontend no cambia.
- **No:** `exists:positions,code` en el FormRequest. Se prefiere el 400 acumulado del Service, consistente con la carga Excel y SPEC 06.
- **Sí:** una sola consulta `whereIn` + `keyBy('code')`. Evita una consulta por empleado.
- **No:** validar que la posición pertenezca a la línea de la tarea. El biométrico ya filtra por línea y existen líneas dependientes (`LineDependencies`).
- **Sí:** salida plana con `position` como código. No rompe a quien lee `position` como string. `positions` no tiene `name`; se expone `code`.
- **No:** objeto anidado `position: { id, code, activity }`. Cambiaría el tipo del campo.
- **Sí:** envolver insert + cambio de status en `DB::transaction`. Convención del proyecto para escrituras multi-tabla; hoy no lo está.
- **No:** vincular con `LineActivity` en esta spec. Se recortó el alcance; va en spec propia junto con la validación de cupos.
- **No:** tests automatizados. Consistente con specs anteriores.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| El biométrico devuelve códigos de posición que no están en `positions` (posiciones nuevas no cargadas) y la confirmación queda bloqueada. | El 400 lista cada código faltante; se cargan por `POST /positions/uploadFile` o CRUD y se reintenta. |
| Filas históricas sin match pierden su código al eliminar la columna. | Antes de migrar en producción, consultar `select distinct position from weekly_plan_task_employees where position not in (select code from positions)` y cargar esas posiciones si importan. |
| Al eliminar una posición referenciada la FK devuelve 500. | Aceptado en esta spec; el bloqueo con 400 queda para otra. |
| Algún consumidor lee `$employee->position` como string (antes columna, ahora relación). | Los consumidores conocidos son el Resource, la tool de IA y `WeeklyPlanTaskAssistantService`; todos pasan por el Resource. Revisar con grep durante la implementación. |

---

## Lo que **no** está en esta spec

- Vínculo del empleado con `LineActivity` y validación de cupos.
- Validación de línea de la posición.
- Validación de status o personal previo en `confirmEmployees`.
- Body con `position_id`.
- Protección del borrado de posiciones referenciadas.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
