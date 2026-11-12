# SPEC 14 — Actividades y cupos de personal por línea

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-11-12
> **Objetivo:** Registrar, con un catálogo global de actividades (`activities`) y una asignación por línea con cantidad (`line_activities`), cuántas personas de cada actividad (recortadores, canasteros, selladores…) necesita cada `Line`.

---

## Por qué existe esta spec

Cada línea trabaja con una dotación fija por actividad. Ejemplo: la línea `1PIQ1A` necesita 10 recortadores, 2 canasteros y 1 sellador. Hoy ese dato no existe como tal.

`positions` tiene un registro por puesto con un texto `activity` (`1PIQ1A001` … `1PIQ1A010` = `RECORTADOR`). Sirve para los códigos del biométrico, pero no es un catálogo: el texto es libre y la cantidad solo sale contando filas. `positions` queda **intacto**.

Esta spec solo crea la **configuración**: el catálogo de actividades y el cupo de cada actividad por línea. La asignación de empleados a cada actividad dentro de una `WeeklyPlanTask` va en una spec posterior, que usará estos cupos.

El patrón es el mismo de SPEC 08: catálogo (`capture_fields`) + asignación por línea (`line_fields`) con rutas `/lines/{code}/…`.

Flujo del usuario:

1. Crea en el catálogo las actividades que usa la planta, cada una con su unidad de medida (`RECORTADOR` en `lbs/h`, `CANASTERO` en `canastas/h`…).
2. Entra a la configuración de una línea y le asigna actividades con su cantidad.
3. El frontend pide `GET /lines/{code}/activities` y muestra la dotación de la línea.
4. Si cambia la dotación, edita la cantidad o quita la actividad de la línea.

---

## Alcance

**Dentro:**

- Tabla `activities`, modelo `Activity` y feature `Activities` (Interface, Service, Provider, Requests, Resources, Controller, `routes/activities.php`).
- CRUD del catálogo: `GET`, `POST`, `GET {id}`, `PUT/PATCH {id}`, `DELETE {id}` en `/activities`.
- Nombre de actividad normalizado a `trim` + mayúsculas y único.
- Unidad de medida obligatoria por actividad (`unit`, texto libre: `lbs/h`, `canastas/h`…).
- Tabla `line_activities`, modelo `LineActivity` y feature `LineActivities` (Interface, Service, Provider, Requests, Resource, Controller), con rutas en `routes/lines.php`.
- `GET /lines/{code}/activities`, `POST /lines/{code}/activities`, `PATCH /line-activities/{id}`, `DELETE /line-activities/{id}`.
- Bloqueo del borrado de una actividad asignada a alguna línea.
- Relaciones `Line::lineActivities()` y `Activity::lineActivities()`.
- Documentación: `public/openapi.yaml`, `references/list-filters.md` y guía nueva `references/line-activities.md`.

**Fuera de alcance (para specs futuras):**

- Asignar empleados a cada actividad en una `WeeklyPlanTask` (cambios en `weekly_plan_task_employees` y `confirmEmployees`). Decisión ya tomada para esa spec: el cupo es un **máximo**, no exacto.
- Relacionar `positions` con `activities` o migrar `positions.activity` al catálogo.
- Seeder o carga Excel (`uploadFile`) de actividades o cupos. Todo se configura por API.
- Columna `order` en `line_activities` para ordenar por proceso.
- Cupos por línea + turno, línea + SKU o por semana.
- Copiar la configuración de una línea a otra.
- Soft delete o `status` en actividades.
- Restringir la escritura a `admin`. Todo queda bajo `jwt.auth`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `create_activities_table`

```php
Schema::create('activities', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->string('unit', 50);
    $table->timestamps();
});
```

| Columna | Significado |
| ------- | ----------- |
| `name`  | Nombre de la actividad en mayúsculas (`RECORTADOR`). Único. |
| `unit`  | Unidad de medida del rendimiento de la actividad (`lbs/h`, `canastas/h`). Texto libre, obligatorio. Se guarda con `trim`, sin cambiar mayúsculas. |

### Migración: `create_line_activities_table`

```php
Schema::create('line_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('line_id')->constrained()->cascadeOnDelete();
    $table->foreignId('activity_id')->constrained();
    $table->unsignedInteger('quantity');
    $table->timestamps();

    $table->unique(['line_id', 'activity_id']);
});
```

| Columna       | Significado |
| ------------- | ----------- |
| `line_id`     | Línea. `cascadeOnDelete`: si se borra la línea, se borran sus cupos. |
| `activity_id` | Actividad del catálogo. Sin cascade: una actividad asignada no se borra (el Service responde 400 antes de llegar a la FK). |
| `quantity`    | Cuántas personas de esa actividad necesita la línea. Entero ≥ 1. |

Una actividad aparece una sola vez por línea (índice único `(line_id, activity_id)`). El Service lo valida antes para responder 400 en vez de un error SQL.

### Modelos

`App\Models\Activity`:

- Fillable: `name`, `unit`.
- Relaciones: `lineActivities()` → `hasMany(LineActivity::class)`.

`App\Models\LineActivity`:

- Fillable: `line_id`, `activity_id`, `quantity`.
- Casts: `quantity` → `integer`.
- Relaciones: `line()` → `Line`, `activity()` → `Activity`.

Cambio en `App\Models\Line`:

- Relación: `lineActivities()` → `hasMany(LineActivity::class)`.

### Normalización del nombre

`CreateActivityRequest` y `UpdateActivityRequest` hacen en `prepareForValidation()`: `name` → `mb_strtoupper(trim($name))`. Así la regla `unique:activities,name` compara el valor ya normalizado: `Recortador ` y `RECORTADOR` chocan.

`unit` → `trim($unit)`. No se pasa a mayúsculas: `lbs/h` se guarda tal cual.

### Endpoints — catálogo (`routes/activities.php`, incluido en `routes/api.php`)

Todos bajo `jwt.auth`. `Route::apiResource('/activities', ActivitiesController::class)->parameters(['activities' => 'id'])`, igual que `capture-fields`.

| Método       | Ruta               | Body   | Respuesta |
| ------------ | ------------------ | ------ | --------- |
| `GET`        | `/activities`      | —      | 200. Con `?limit=` → `PaginatedActivitiesResource`; sin `limit` → `ActivityResource::collection`. Orden por `name`. |
| `POST`       | `/activities`      | `name`, `unit` | 201, `ActivityResource` |
| `GET`        | `/activities/{id}` | —      | 200, `ActivityResource` |
| `PUT/PATCH`  | `/activities/{id}` | `name` y/o `unit` | 200, `data: null` |
| `DELETE`     | `/activities/{id}` | —      | 200, `data: null` |

Filtro de `GET /activities`: `name` (parcial, `LIKE %valor%`, insensible a mayúsculas porque el valor se compara con `mb_strtoupper`).

Mensajes de éxito: `Actividades Obtenidas Correctamente`, `Actividad Creada Correctamente`, `Actividad Obtenida Correctamente`, `Actividad Actualizada Correctamente`, `Actividad Eliminada Correctamente`.

`CreateActivityRequest`:

```text
name   string   required|max:100|unique:activities,name
unit   string   required|max:50
```

`UpdateActivityRequest` (se puede mandar solo uno de los dos campos):

```text
name   string   sometimes|required|max:100|unique:activities,name,{id}
unit   string   sometimes|required|max:50
```

Mensajes de validación en español, con el estilo de `CreateLineFieldRequest` (ej. `El nombre es obligatorio.`, `La actividad ya existe.`, `La unidad de medida es obligatoria.`).

Reglas del Service (`ActivitiesService`):

- `getActivityById`: inexistente → `NotFoundError('La actividad no existe')`.
- `deleteActivityById`: si tiene filas en `line_activities` → `BadRequestError('No se puede eliminar una actividad asignada a líneas')`.

`ActivityResource`:

```php
[
    'id' => $this->id,
    'name' => $this->name,
    'unit' => $this->unit,
]
```

`PaginatedActivitiesResource`: `data`, `total`, `currentPage`, `lastPage`.

### Endpoints — cupos por línea (`routes/lines.php`, grupo `// FUNCTIONALITYS`)

Todos bajo `jwt.auth`. Los atiende `LineActivitiesController`.

| Método   | Ruta                          | Body                       | Respuesta |
| -------- | ----------------------------- | -------------------------- | --------- |
| `GET`    | `/lines/{code}/activities`    | —                          | 200, `LineActivityResource::collection`, orden por `activities.name` |
| `POST`   | `/lines/{code}/activities`    | `activity_id`, `quantity`  | 201, `LineActivityResource` |
| `PATCH`  | `/line-activities/{id}`       | `quantity`                 | 200, `data: null` |
| `DELETE` | `/line-activities/{id}`       | —                          | 200, `data: null` |

Mensajes de éxito: `Actividades de la Línea Obtenidas Correctamente`, `Actividad Asignada Correctamente`, `Actividad de la Línea Actualizada Correctamente`, `Actividad Quitada de la Línea Correctamente`.

`CreateLineActivityRequest`:

```text
activity_id   integer   required|exists:activities,id
quantity      integer   required|min:1
```

`UpdateLineActivityRequest`:

```text
quantity      integer   required|min:1
```

Reglas del Service (`LineActivitiesService`, inyecta `LinesServiceInterface` y `ActivitiesServiceInterface`):

- Línea inexistente → `NotFoundError` (reutiliza `LinesServiceInterface::getLineByCode`).
- Actividad inexistente → `NotFoundError('La actividad no existe')` (reutiliza `ActivitiesServiceInterface::getActivityById`).
- Actividad ya asignada a la línea → `BadRequestError('La actividad ya está asignada a la línea')`.
- `getLineActivityById`: inexistente → `NotFoundError('La actividad de la línea no existe')`.
- `PATCH` solo cambia `quantity`. Para cambiar la actividad se quita y se asigna otra.

`LineActivityResource`:

```php
[
    'id' => $this->id,
    'line_id' => $this->line_id,
    'activity_id' => $this->activity_id,
    'activity' => $this->activity->name,
    'unit' => $this->activity->unit,
    'quantity' => $this->quantity,
]
```

El Service carga `with('activity')` en el listado para evitar N+1.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Catálogo: migración y modelo.** `php artisan make:model Activity -m --no-interaction`. Columnas e índice del modelo de datos. Fillable. Verificación: `php artisan migrate` sin error y `database-schema` muestra `activities`.
2. **Feature `Activities`.** Interface, Service, Provider registrado en `bootstrap/providers.php`, `CreateActivityRequest` / `UpdateActivityRequest` con normalización, `ActivityResource`, `PaginatedActivitiesResource`, `ActivitiesController` y `routes/activities.php` incluido en `routes/api.php`. Sin la regla de borrado en uso (aún no existe `line_activities`). Verificación: `php artisan route:list --path=activities` muestra las 5 rutas; `POST` con `name: "recortador "` y `unit: "lbs/h"` guarda `RECORTADOR` con `lbs/h`.
3. **Cupos: migración y modelo.** `php artisan make:model LineActivity -m --no-interaction`. Columnas, FKs e índice único. Fillable, cast y relaciones. `lineActivities()` en `Line` y en `Activity`. Verificación: `php artisan migrate` sin error.
4. **Feature `LineActivities`.** Interface, Service, Provider registrado, `CreateLineActivityRequest` / `UpdateLineActivityRequest`, `LineActivityResource`, `LineActivitiesController` y las 4 rutas en `routes/lines.php` (grupo `// FUNCTIONALITYS`). Verificación: asignar `RECORTADOR` con `quantity: 10` a una línea y verla en `GET /lines/{code}/activities`.
5. **Borrado protegido.** En `ActivitiesService::deleteActivityById`, validar que no tenga filas en `line_activities`. Verificación: borrar una actividad asignada → 400.
6. **Documentación.** Endpoints en `public/openapi.yaml`, filtro `name` de `GET /api/activities` en `references/list-filters.md` y guía `references/line-activities.md` (flujo, endpoints, ejemplos de request/response y errores), con el estilo de `references/line-capture-fields.md`.
7. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `activities` con `name` único, `unit` no nullable y `line_activities` con FKs a `lines` (cascade) y `activities` (sin cascade) y el índice único `(line_id, activity_id)`.
- [ ] `php artisan route:list` muestra las 5 rutas de `/activities` y las 4 de cupos bajo `jwt.auth`.
- [ ] `POST /activities` con `name: "  recortador "` y `unit: " lbs/h "` responde 201 y guarda `RECORTADOR` con `unit` = `lbs/h`.
- [ ] `POST /activities` sin `unit` responde 422 `La unidad de medida es obligatoria.`.
- [ ] `PATCH /activities/{id}` con solo `unit: "canastas/h"` cambia la unidad y deja el nombre igual.
- [ ] `POST /activities` con `name: "Recortador"` cuando ya existe `RECORTADOR` responde 422 con mensaje en español.
- [ ] `PUT /activities/{id}` con el mismo nombre que ya tiene no falla por `unique`.
- [ ] `GET /activities` sin `limit` devuelve una colección ordenada por `name`; con `?limit=5` devuelve `data`, `total`, `currentPage`, `lastPage`.
- [ ] `GET /activities?name=rec` devuelve `RECORTADOR`.
- [ ] `GET /activities/{id}` inexistente → 404 `La actividad no existe`.
- [ ] `DELETE /activities/{id}` de una actividad sin cupos → 200 y la fila desaparece.
- [ ] `DELETE /activities/{id}` de una actividad asignada a una línea → 400 `No se puede eliminar una actividad asignada a líneas` y la fila sigue en BD.
- [ ] `POST /lines/{code}/activities` con `activity_id` válido y `quantity: 10` → 201 con `activity: "RECORTADOR"`, `unit: "lbs/h"` y `quantity: 10`.
- [ ] `POST /lines/{code}/activities` con `quantity: 0` → 422.
- [ ] `POST /lines/{code}/activities` con una actividad ya asignada a esa línea → 400 `La actividad ya está asignada a la línea`.
- [ ] La misma actividad se puede asignar a dos líneas distintas.
- [ ] `POST /lines/{code}/activities` con línea inexistente → 404.
- [ ] `GET /lines/{code}/activities` devuelve solo los cupos de esa línea, ordenados por nombre de actividad, sin consultas N+1.
- [ ] `PATCH /line-activities/{id}` con `quantity: 12` actualiza solo la cantidad.
- [ ] `PATCH` o `DELETE /line-activities/{id}` inexistente → 404 `La actividad de la línea no existe`.
- [ ] `DELETE /line-activities/{id}` borra el cupo y la actividad sigue en el catálogo.
- [ ] Borrar una línea borra sus filas de `line_activities`.
- [ ] La tabla `positions` y sus endpoints no cambian.
- [ ] `public/openapi.yaml`, `references/list-filters.md` y `references/line-activities.md` documentan los endpoints nuevos.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** tablas nuevas; `positions` queda intacto. `positions` sirve para los códigos de puesto del biométrico y no se toca en esta spec.
- **No:** reemplazar `positions` por la nueva estructura. Rompería la integración con el biométrico y los datos cargados.
- **No:** derivar los cupos agrupando `positions` por `activity`. El texto es libre y la cantidad depende de cuántas filas se cargaron.
- **Sí:** catálogo global `activities` + `line_activities` con `quantity`. Evita typos entre líneas y permite reportar por actividad. Mismo patrón que SPEC 08.
- **No:** texto libre por línea. `Recortador` y `RECORTADOR` serían actividades distintas.
- **No:** una fila por cupo individual (como `positions`). La cantidad sería un conteo implícito y editarla obligaría a crear o borrar filas.
- **Sí:** nombres `Activity` / `LineActivity`. Coincide con `positions.activity`. Se evita "tarea" para no confundir con `WeeklyPlanTask`.
- **Sí:** nombre en mayúsculas y único, normalizado en el FormRequest. Coincide con los datos de `positions` y deja que `unique` compare el valor final.
- **Sí:** columna `unit` obligatoria en `activities`, texto libre (`lbs/h`, `canastas/h`). La unidad es de la actividad, no de la línea: un recortador mide igual en todas.
- **No:** enum `ActivityUnit`. Cada unidad nueva requeriría cambiar código.
- **No:** catálogo `units` con FK. Una feature CRUD más para un dato que hoy solo se muestra.
- **Sí:** `unit` solo con `trim`, sin mayúsculas. Las unidades se leen mejor en minúsculas (`lbs/h`).
- **Sí:** `UpdateActivityRequest` con `sometimes|required`. Permite cambiar solo la unidad o solo el nombre.
- **Sí:** una actividad una sola vez por línea. La cantidad ya expresa cuántas personas.
- **Sí:** `quantity` entero ≥ 1. Un cupo de 0 equivale a no tener la actividad: se quita.
- **No:** columna `order`. El listado se ordena por nombre; el orden por proceso queda para otra spec si se necesita.
- **Sí:** `PATCH /line-activities/{id}` solo cambia `quantity`. Cambiar la actividad es quitar y asignar.
- **Sí:** bloquear el borrado de una actividad asignada (400). Sin esto la FK devuelve 500.
- **Sí:** `cascadeOnDelete` desde `lines`. Los cupos de una línea borrada no tienen sentido.
- **No:** soft delete ni `status` en actividades. No hay historial que proteger todavía.
- **Sí:** rutas estilo SPEC 08: `apiResource` para el catálogo y `/lines/{code}/activities` + `/line-activities/{id}` para los cupos.
- **No:** `apiResource` plano para `line-activities`. El cupo siempre se trabaja en el contexto de una línea.
- **Sí:** solo `jwt.auth`, igual que `positions` y SPEC 08.
- **No:** seeder ni carga Excel. La configuración inicial se hace por API.
- **Sí:** separar la asignación de empleados en `WeeklyPlanTask` en otra spec. Esta deja los cupos cargados para probarla.
- **Sí (para la spec de asignación):** el cupo es un máximo. No se asignan más empleados que `quantity`; menos sí (faltó gente).
- **No:** tests automatizados. Consistente con specs anteriores; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| `activities` choca con nombres de paquetes (ej. `spatie/laravel-activitylog` usa `activity_log`). | Hoy no está instalado. Si se instala, su tabla es `activity_log`, distinta de `activities`. |
| El catálogo duplica información con `positions.activity` y pueden divergir (`RECORTADOR` en uno, `RECORTADORES` en otro). | Se acepta en esta spec. Vincular ambos va en spec propia. |
| `mb_strtoupper` sobre nombres con tildes (`EMPACADOR DE CAÑA`) depende de la extensión `mbstring`. | `mbstring` es requisito de Laravel; siempre está. |
| Al ser texto libre, la misma unidad puede escribirse distinto (`lbs/h`, `lb/h`, `Lbs/H`). | Se acepta: el catálogo de actividades es corto y lo administra poca gente. Si se van a hacer cálculos o reportes por unidad, se migra a enum o catálogo en otra spec. |
| La spec de asignación necesitará cupos cargados en todas las líneas. | El usuario los carga por API antes de implementar esa spec. |

---

## Lo que **no** está en esta spec

- Asignación de empleados por actividad en `WeeklyPlanTask`.
- Vínculo entre `positions` y `activities`.
- Seeder o carga Excel de actividades y cupos.
- Orden por proceso de las actividades de una línea.
- Cupos por turno, SKU o semana.
- Copiar cupos entre líneas.
- Soft delete o `status` de actividades.
- Restricción a `admin`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
