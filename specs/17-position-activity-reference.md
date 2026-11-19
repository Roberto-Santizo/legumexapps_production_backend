# SPEC 17 — Referencia al catálogo `activities` en `positions`

> **Estado:** Aprobado
> **Depende de:** SPEC 14, SPEC 16
> **Fecha:** 2026-11-18
> **Objetivo:** Reemplazar el texto libre `positions.activity` por la FK `positions.activity_id` al catálogo `activities`, exigiendo que la actividad esté asignada a la línea de la posición.

---

## Por qué existe esta spec

`positions.activity` es un `string` libre (`RECORTADOR`, `CANASTERO`…). SPEC 14 creó el catálogo `activities` y su asignación por línea (`line_activities`), pero dejó `positions` intacto. Hoy no hay vínculo entre el puesto del biométrico y el catálogo.

Esta spec cambia el texto por una FK a `activities`. La actividad de una posición debe estar asignada a su línea en `line_activities`. Por decisión del usuario no hay backfill: todas las posiciones existentes quedan con `activity_id = null` y se recargan por CRUD o Excel.

Flujo:

1. Se migra: se agrega `positions.activity_id` (nullable) y se elimina `positions.activity`.
2. El usuario asigna actividades a cada línea (`POST /lines/{code}/activities`, SPEC 14).
3. Crea o edita posiciones con `activity_id` (CRUD) o con la columna `actividad` por nombre (Excel).
4. El backend valida que la actividad exista y esté asignada a la línea de la posición.
5. `GET /positions` y `GET /weekly-plan-tasks/{id}/employees` leen la actividad desde el catálogo.

---

## Alcance

**Dentro:**

- Migración `replace_activity_with_activity_id_on_positions_table`: agrega `activity_id` (FK nullable a `activities`, sin cascade) y elimina `activity`. Sin backfill.
- Modelo `Position`: Fillable con `activity_id` en lugar de `activity`; relación `activity()`. Modelo `Activity`: relación `positions()`.
- `CreatePositionRequest` y `UpdatePositionRequest`: `activity_id` obligatorio en lugar de `activity`.
- `PositionsService::createPosition` y `updatePositionById`: 400 si la actividad no está asignada a la línea de la posición.
- `PositionsService::uploadFile`: la columna `actividad` se resuelve por nombre contra `activities.name`, con error por fila si no existe o no está asignada a la línea.
- Filtro `?activity=` de `GET /positions` sobre `activities.name`. Eager loading de `line` y `activity`.
- `PositionResource`: `activity_id`, `activity` (= `activities.name`), `unit`.
- `WeeklyPlanTaskEmployeeResource.position_activity` lee `position.activity.name`; eager loading `position.activity` en `WeeklyPlanTaskEmployeesService::getTaskEmployees` y `WeeklyPlanTaskAssistantService::getTaskEmployees`.
- `ActivitiesService::deleteActivityById`: 400 si alguna posición la referencia.
- `LineActivitiesService::deleteLineActivityById`: 400 si hay posiciones de esa línea con esa actividad.
- `ActivitiesService::getActivities`: filtro nuevo `?line_id=` que devuelve solo las actividades asignadas a esa línea en `line_activities`. Lo usa el frontend para ofrecer, al crear o editar una posición, solo actividades válidas para su línea.
- `CatalogSeeder`: descarta la clave `activity` de las filas de `positions`. `PositionFactory`: sin `activity`.
- Documentación: `public/openapi.yaml`, `references/base-data-excel-bulk-upload.md`, `references/line-activities.md`, `references/weekly-plan-task-employees.md`, `references/list-filters.md`.

**Fuera de alcance (para specs futuras):**

- Backfill de `activity_id` en posiciones existentes (por nombre u otro criterio).
- Volver `activity_id` NOT NULL.
- Validar coherencia entre `positions.activity_id` y la `line_activity_id` elegida al confirmar empleados (SPEC 16).
- Aceptar actividades de líneas dependientes (`LineDependencies`).
- Revalidar posiciones existentes cuando cambia la asignación de actividades de una línea, salvo el bloqueo del `DELETE`.
- Crear actividades faltantes en el catálogo desde el Excel de posiciones.
- `ActivityFactory`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `replace_activity_with_activity_id_on_positions_table`

```php
public function up(): void
{
    Schema::table('positions', function (Blueprint $table) {
        $table->foreignId('activity_id')->nullable()->after('code')->constrained();
        $table->dropColumn('activity');
    });
}

public function down(): void
{
    Schema::table('positions', function (Blueprint $table) {
        $table->string('activity')->nullable()->after('code');
    });

    DB::table('positions')->update([
        'activity' => DB::raw('(select activities.name from activities where activities.id = positions.activity_id)'),
    ]);

    Schema::table('positions', function (Blueprint $table) {
        $table->dropConstrainedForeignId('activity_id');
    });
}
```

| Columna       | Significado |
| ------------- | ----------- |
| `activity_id` | Actividad del catálogo que cubre el puesto. FK a `activities` sin cascade. Nullable porque las posiciones existentes no se migran; toda posición nueva o editada lo lleva. |

- El `up` pierde el texto de todas las posiciones existentes. Aceptado por el usuario.
- El `down` restaura `activity` como string nullable a partir del catálogo. Las posiciones con `activity_id = null` quedan con `activity = null`; el texto original no se recupera.

### Modelos

`App\Models\Position`:

- Fillable: `code`, `activity_id`, `line_id`, `status`.
- Relación nueva: `activity()` → `belongsTo(Activity::class)`.

`App\Models\Activity`:

- Relación nueva: `positions()` → `hasMany(Position::class)`.

### Regla de línea

Una actividad es válida para una posición si existe `line_activities` con `line_id` = `positions.line_id` y `activity_id` = `positions.activity_id`. Se valida en el Service (necesita `line_id` y `activity_id` juntos).

Mensaje: `La actividad 'RECORTADOR' no está asignada a la línea 'L01'` (nombre de la actividad y `lines.code`).

### Endpoints `POST /positions` y `PUT/PATCH /positions/{id}`

Body:

```json
{ "code": "1PIQ1A001", "activity_id": 4, "line_id": 3, "status": true }
```

`CreatePositionRequest` y `UpdatePositionRequest` reemplazan la regla de `activity` por:

```text
activity_id   integer   required|integer|exists:activities,id
```

Mensajes: `La actividad es requerida.`, `La actividad debe ser un número entero.`, `La actividad no existe.`

Reglas en `PositionsService`:

- `createPosition`: valida la regla de línea con `line_id` y `activity_id` del body; luego crea.
- `updatePositionById`: `getPositionById` (404 como hoy); valida la regla de línea con `line_id` y `activity_id` del body; luego actualiza.

Respuestas sin cambios.

### Endpoint `GET /positions`

- `with(['line', 'activity'])` en la consulta.
- Filtro `?activity=`: `whereHas('activity', fn ($q) => $q->where('name', 'LIKE', '%'.mb_strtoupper($value).'%'))`. Posiciones con `activity_id = null` no aparecen al filtrar.

`PositionResource`:

```php
[
    'id' => $this->id,
    'code' => $this->code,
    'activity_id' => $this->activity_id,
    'activity' => $this->activity?->name,
    'unit' => $this->activity?->unit,
    'line_id' => $this->line_id,
    'line' => $this->line->name,
    'status' => $this->status,
]
```

`activity` sigue siendo string (ahora `activities.name`) o `null` en posiciones sin migrar.

### Endpoint `POST /positions/uploadFile`

Encabezados sin cambios (`codigo`, `actividad`, `linea`). `actividad` se resuelve por nombre:

1. Antes de recorrer filas: `Activity::pluck('id', 'name')` y un set de pares `line_id|activity_id` desde `line_activities` (dos consultas fijas).
2. Por fila: `mb_strtoupper(trim($actividad))`.
3. Errores por fila, acumulados como hoy:
   - `Línea N: la actividad 'X' no existe`.
   - `Línea N: la actividad 'X' no está asignada a la línea 'L01'` (solo si la línea y la actividad existen).
4. El insert usa `activity_id` en lugar de `activity`.

Si hay errores → un único `BadRequestError` con los mensajes unidos por `PHP_EOL`; no se inserta nada.

### Endpoint `GET /weekly-plan-tasks/{id}/employees` (SPEC 16)

`WeeklyPlanTaskEmployeeResource`: `'position_activity' => $this->position?->activity?->name`. El resto sin cambios.

Ambos `getTaskEmployees` cargan `with(['position.activity', 'lineActivity.activity'])`. La tool de IA `GetWeeklyPlanTaskEmployees` usa el mismo Resource.

### Endpoint `DELETE /activities/{id}` (SPEC 14)

`ActivitiesService::deleteActivityById`, después de la regla existente: si `$activity->positions()->exists()` → `BadRequestError('No se puede eliminar una actividad asignada a posiciones')`.

### Endpoint `DELETE /line-activities/{id}` (SPEC 14 / 16)

`LineActivitiesService::deleteLineActivityById`, después de la regla existente de personal asignado: si `Position::where('line_id', $lineActivity->line_id)->where('activity_id', $lineActivity->activity_id)->exists()` → `BadRequestError('No se puede quitar una actividad con posiciones asignadas')`.

### Endpoint `GET /activities` (SPEC 14) — filtro `line_id`

`ActivitiesService::getActivities` agrega, junto al filtro `name`:

```php
if ($request->filled('line_id')) {
    $query->whereHas('lineActivities', fn ($q) => $q->where('line_id', $request->query('line_id')));
}
```

| Param     | Tipo    | Comparación | Descripción |
| --------- | ------- | ----------- | ----------- |
| `line_id` | integer | Exacta      | Id de la línea. Devuelve solo las actividades asignadas a esa línea en `line_activities`. |

- Se combina con `name` y con `?limit=` (paginado) como el resto de filtros.
- `line_id` inexistente o línea sin actividades → lista vacía (200), sin 404.
- El orden por `name` y el `ActivityResource` no cambian.

### Seeder y factory

- `CatalogSeeder::seedTable`: para `positions`, quita la clave `activity` de cada fila antes del upsert. `positions.json` no cambia. El upsert no toca `activity_id`, así que re-ejecutar el seeder conserva las actividades ya cargadas.
- `PositionFactory`: elimina `activity`; no define `activity_id` (queda `null`).

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración, modelos, seeder y factory.** `php artisan make:migration replace_activity_with_activity_id_on_positions_table --no-interaction` con el `up`/`down` del modelo de datos. Fillable y `activity()` en `Position`; `positions()` en `Activity`. En el mismo paso: `PositionResource` y `WeeklyPlanTaskEmployeeResource` leen la actividad por relación (la columna deja de existir), `CatalogSeeder` descarta `activity`, `PositionFactory` sin `activity`. Verificación: `php artisan migrate` sin error; `database-schema` muestra `positions.activity_id` y no `activity`; `php artisan db:seed --class=CatalogSeeder` sin error.
2. **CRUD.** `activity_id` en ambos FormRequests; regla de línea en `createPosition` y `updatePositionById`. Verificación: casos de los criterios de aceptación para `POST` y `PATCH /positions`.
3. **Listado.** Eager loading `line` y `activity`; filtro `?activity=` sobre `activities.name`. Verificación: `GET /positions?activity=recor` devuelve solo posiciones con actividad `RECORTADOR`, sin N+1.
4. **Excel.** Resolución por nombre y regla de línea en `uploadFile`. Verificación: casos de los criterios de aceptación para `uploadFile`.
5. **Lecturas de personal.** `with(['position.activity', 'lineActivity.activity'])` en ambos `getTaskEmployees`. Verificación: `GET /weekly-plan-tasks/{id}/employees` devuelve `position_activity` sin N+1.
6. **Borrados protegidos.** Reglas nuevas en `deleteActivityById` y `deleteLineActivityById`. Verificación: casos de los criterios de aceptación para ambos `DELETE`.
7. **Filtro por línea en el catálogo.** `?line_id=` en `ActivitiesService::getActivities` con `whereHas('lineActivities')`. Verificación: `GET /activities?line_id=3` devuelve solo las actividades asignadas a la línea 3; con `&name=rec` se combinan; `?line_id=99999` → 200 con lista vacía.
8. **Documentación.** `public/openapi.yaml` (body de `POST`/`PUT /positions`, schema de posición, 400 de `uploadFile`, 400 nuevos de `DELETE /activities/{id}` y `DELETE /line-activities/{id}`, descripción de `position_activity`, query param `line_id` en `GET /activities`), `references/base-data-excel-bulk-upload.md` (columna `Actividad` por nombre del catálogo y sus errores), `references/line-activities.md` (nuevos 400), `references/weekly-plan-task-employees.md` (origen de `position_activity`) y `references/list-filters.md` (filtro `line_id` de actividades).
9. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` agrega `positions.activity_id` (FK nullable a `activities`, sin cascade) y elimina `positions.activity`; todas las filas existentes quedan con `activity_id = null`.
- [ ] `php artisan migrate:rollback` restaura `positions.activity` (string nullable, con `activities.name` donde había FK) y elimina `activity_id`.
- [ ] `php artisan db:seed --class=CatalogSeeder` termina sin error y no modifica `activity_id` de posiciones existentes.
- [ ] `POST /positions` sin `activity_id` → 422 `La actividad es requerida.`; con un id inexistente → 422 `La actividad no existe.`
- [ ] `POST /positions` con una actividad asignada a la línea → 201 con `activity_id`, `activity` y `unit`.
- [ ] `POST /positions` con una actividad no asignada a la línea → 400 `La actividad 'X' no está asignada a la línea 'L'`; no crea nada.
- [ ] `PUT/PATCH /positions/{id}` aplica las mismas validaciones, usando el `line_id` del body.
- [ ] `PUT/PATCH /positions/{id}` sobre una posición con `activity_id = null` le asigna la actividad.
- [ ] `GET /positions` devuelve `activity_id`, `activity` (nombre del catálogo o `null`) y `unit`, sin N+1.
- [ ] `GET /positions?activity=recor` devuelve solo posiciones cuya actividad del catálogo contiene `RECOR`.
- [ ] `POST /positions/uploadFile` con actividades existentes y asignadas a sus líneas → crea las posiciones con `activity_id` correcto; `recortador ` y `RECORTADOR` resuelven igual.
- [ ] `POST /positions/uploadFile` con una actividad inexistente y otra no asignada a su línea → un único 400 con ambos mensajes por número de línea; no inserta nada.
- [ ] `uploadFile` resuelve actividades y asignaciones de línea con un número fijo de consultas, sin importar cuántas filas lleguen.
- [ ] `GET /weekly-plan-tasks/{id}/employees` devuelve `position_activity` = `activities.name` de la posición (o `null`), sin N+1.
- [ ] La tool de IA `GetWeeklyPlanTaskEmployees` devuelve los mismos campos sin error.
- [ ] `DELETE /activities/{id}` referenciada por una posición → 400 `No se puede eliminar una actividad asignada a posiciones`.
- [ ] `DELETE /line-activities/{id}` con posiciones de esa línea y esa actividad → 400 `No se puede quitar una actividad con posiciones asignadas`; sin posiciones ni personal asignado → 200.
- [ ] `GET /activities?line_id=N` devuelve solo las actividades con registro en `line_activities` para la línea `N`, ordenadas por `name`.
- [ ] `GET /activities?line_id=N&name=rec` aplica ambos filtros; con `&limit=` devuelve `PaginatedActivitiesResource`.
- [ ] `GET /activities?line_id=N` con una línea inexistente o sin actividades → 200 con lista vacía.
- [ ] `GET /activities` sin `line_id` devuelve el catálogo completo, como hoy.
- [ ] Ningún archivo de `app/` lee `positions.activity` como columna (`grep` sin coincidencias).
- [ ] `public/openapi.yaml`, `references/base-data-excel-bulk-upload.md`, `references/line-activities.md`, `references/weekly-plan-task-employees.md` y `references/list-filters.md` reflejan el cambio.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** reemplazar el string por `activity_id`. Elegido por el usuario: una sola fuente de verdad, el catálogo.
- **No:** mantener ambos campos. Divergirían.
- **Sí:** sin backfill; todas las posiciones existentes quedan en `null`. Elegido por el usuario tras advertir la pérdida del texto; se recargan por CRUD o Excel.
- **No:** backfill por nombre (`UPPER(TRIM(activity)) = activities.name`). Descartado por el usuario.
- **Sí:** columna nullable. Necesario sin backfill.
- **Sí:** `activity_id` obligatorio en Create y Update. Toda posición nueva o editada queda vinculada.
- **Sí:** la actividad debe estar asignada a la línea de la posición en `line_activities`. Elegido por el usuario: coherencia con los cupos de SPEC 16. Obliga a cargar las actividades de la línea antes que sus posiciones.
- **Sí:** regla de línea en el Service con 400, `exists:` en el FormRequest para la existencia. La regla de línea depende de dos campos; la existencia es simple y da 422 como el resto del CRUD.
- **Sí:** Excel con la columna `actividad` por nombre, normalizado con `mb_strtoupper(trim())`. Mantiene la plantilla actual; coincide con la normalización del catálogo (SPEC 14).
- **No:** columna `actividad_id` en el Excel. Menos amigable.
- **No:** crear actividades faltantes desde el Excel. El catálogo se administra por `/activities`.
- **Sí:** salida plana: `activity` sigue siendo string (`activities.name`), más `activity_id` y `unit`. No cambia el tipo del campo.
- **No:** objeto anidado `activity: { id, name, unit }`. Cambiaría el tipo.
- **Sí:** `position_activity` en `WeeklyPlanTaskEmployeeResource` pasa a leer del catálogo. La columna desaparece.
- **Sí:** 400 al borrar una `Activity` referenciada por posiciones. Evita el 500 de la FK.
- **Sí:** 400 al quitar una `LineActivity` con posiciones de esa línea y actividad. Mantiene la regla de línea sin revalidar posiciones.
- **Sí:** filtro `?line_id=` (id exacto) en `GET /activities`. Pedido por el usuario: el frontend filtra por línea y así solo ofrece actividades que pasan la regla de línea.
- **No:** filtrar por nombre o código de línea (`line`, `lineCode`) como otros listados. El formulario de posición ya trabaja con `line_id`.
- **Sí:** `line_id` sin match → lista vacía, no 404. Es un filtro, no una búsqueda por id.
- **No:** endpoint nuevo para esto. `GET /lines/{code}/activities` (SPEC 14) ya existe pero devuelve `LineActivity` con cupo y usa `code`; el filtro reusa el catálogo y su Resource.
- **Sí:** `CatalogSeeder` descarta `activity` sin editar `positions.json`. Cambio mínimo; el JSON conserva el dato original por si se hace backfill en otra spec.
- **No:** `ActivityFactory` en esta spec. `PositionFactory` deja `activity_id` en `null`.
- **No:** validar coherencia entre `positions.activity_id` y la `line_activity_id` de la asignación (SPEC 16). Otra spec.
- **No:** tests automatizados. Consistente con specs anteriores.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Al migrar, todas las posiciones pierden su actividad y `GET /positions` muestra `activity: null`. | Aceptado por el usuario. Recargar por Excel tras migrar; `positions.json` conserva los textos originales como referencia. |
| Una línea sin actividades asignadas (SPEC 14) no permite crear ni editar sus posiciones. | El 400 indica actividad y línea; asignar por `POST /lines/{code}/activities` antes. |
| El body de `POST`/`PUT /positions` cambia (`activity` → `activity_id`) y el frontend actual recibe 422. | Desplegar junto con el cambio del frontend. |
| Editar el `status` o el código de una posición sin migrar exige enviar `activity_id` (Update lo requiere). | Aceptado: obliga a completar la actividad al primer cambio. |
| `CatalogSeeder` en una base nueva crea posiciones sin actividad. | Aceptado; se cargan después por Excel o CRUD. |

---

## Lo que **no** está en esta spec

- Backfill de actividades en posiciones existentes.
- `activity_id` NOT NULL.
- Coherencia `positions.activity_id` ↔ `line_activity_id` de las asignaciones.
- Líneas dependientes.
- Revalidación de posiciones al cambiar actividades de una línea (fuera del bloqueo del `DELETE`).
- Alta de actividades desde el Excel de posiciones.
- `ActivityFactory`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
