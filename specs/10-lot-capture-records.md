# SPEC 10 — Captura por lote de materia prima (familia `lot`)

> **Estado:** Implementado
> **Depende de:** SPEC 08, SPEC 09
> **Fecha:** 2026-10-09
> **Objetivo:** Registrar, mientras la tarea está en progreso, una fila por lote (GRN) de materia prima en las líneas `lot`, validada contra los `line_fields` de la línea y con `% recuperación`, `% sobremadurez` y `saldo GRN` resueltos por `LotCalculator`.

---

## Por qué existe esta spec

Las líneas de recorte (Recorte LD, Recorte Jugos, Ejotera) ya se pueden configurar como `lot` (SPEC 08), pero no tienen dónde capturar. En los Excel, cada fila de "1. PRODUCCIÓN LÍNEA" es un lote GRN que entra a la línea:

| Columna Excel | key | Origen |
| ------------- | --- | ------ |
| Fecha de ingreso | `entry_date` | captura |
| Lote (GRN) | `lot` | captura |
| Hora | `recorded_at` | captura |
| Peso de libras al ingreso | `intake_lbs` | captura |
| MP aplicada | `applied_raw_lbs` | captura |
| Libras recortadas | `trimmed_lbs` | captura |
| Libras sobremaduro | `overripe_lbs` | captura |
| % Recuperación | `recovery_pct` | `trimmed_lbs / applied_raw_lbs` |
| % Sobremadurez | `overripe_pct` | `overripe_lbs / applied_raw_lbs` |
| Saldo GRN | `grn_balance` | `intake_lbs − applied_raw_lbs` |

Esta spec replica para `lot` el flujo de la SPEC 09: body `values`, validación dinámica, campos custom en `extra_values` y calculados guardados al escribir. Además extrae la lógica que `pallet` y `lot` comparten, para que `product` (SPEC 11) la reutilice.

Flujo del usuario:

1. La línea es `lot` y tiene campos configurados.
2. El frontend pide `GET /lines/{code}/fields` y arma el formulario.
3. Por cada lote manda `POST /weekly-plan-task-lot-records` con `values: { key: valor }`.
4. El backend valida, separa columnas de `extra_values`, aplica las reglas entre campos, calcula y guarda.
5. El avance de la tarea (`recorded_pounds`) suma las libras recortadas.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_lot_records`, modelo `WeeklyPlanTaskLotRecord` y relación `WeeklyPlanTask::lotRecords()`.
- Feature `WeeklyPlanTaskLotRecords`: Interface, Service, Provider, Requests, Resource y Controller, con `apiResource('/weekly-plan-task-lot-records')` en `routes/weeklyplantasks.php`.
- `App\Calculators\LotCalculator`.
- Reglas de negocio: lote único por tarea, MP aplicada ≤ libras al ingreso, y recortadas + sobremaduro ≤ MP aplicada.
- Lógica compartida que se extrae:
  - `CaptureValueRules::forTask()` para los Requests.
  - `WeeklyPlanTasksService::getCaptureLineFields()` para los Services.
  - `CaptureType::unitLabel()` para el mensaje de familia incorrecta.
  - Las tomas de rendimiento (`pallet`) pasan a usarlos sin cambiar su comportamiento.
- `recorded_pounds` de la tarea = suma de `net_weight` de las tarimas + suma de `trimmed_lbs` de los lotes.
- Documentación en `public/openapi.yaml` y guía `references/weekly-plan-task-lot-records.md`.

**Fuera de alcance (para specs futuras):**

- Captura de la familia `product` (SPEC 11).
- Arrastrar el saldo GRN entre tareas o días, o llevar el inventario de materia prima por GRN.
- Validar el GRN contra un catálogo de recepciones.
- Totales por tarea (fila TOTALES del Excel: sumas y % de recuperación global). El frontend los calcula con el listado.
- Recalcular registros guardados cuando cambia la configuración de la línea.
- Módulos extra de las líneas de recorte (Llenado tanque/prensa, tiempos muertos por área).
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_lot_records_table`

```php
Schema::create('weekly_plan_task_lot_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->date('entry_date')->nullable();
    $table->string('lot', 50)->nullable();
    $table->time('recorded_at')->nullable();
    $table->float('intake_lbs')->nullable();
    $table->float('applied_raw_lbs')->nullable();
    $table->float('trimmed_lbs')->nullable();
    $table->float('overripe_lbs')->nullable();
    $table->float('recovery_pct')->nullable();
    $table->float('overripe_pct')->nullable();
    $table->float('grn_balance')->nullable();
    $table->string('observations', 500)->nullable();
    $table->json('extra_values')->nullable();
    $table->timestamps();

    $table->unique(['weekly_plan_task_id', 'lot']);
});
```

El índice único deja varias filas con `lot = null` en la misma tarea (`pgsql` y `sqlite` no comparan nulls). El Service además valida el duplicado para responder 400 y no un error SQL.

### Modelo `App\Models\WeeklyPlanTaskLotRecord`

- Fillable: `weekly_plan_task_id`, `user_id`, `entry_date`, `lot`, `recorded_at`, `intake_lbs`, `applied_raw_lbs`, `trimmed_lbs`, `overripe_lbs`, `recovery_pct`, `overripe_pct`, `grn_balance`, `observations`, `extra_values`.
- Casts: `entry_date` → `date`, `extra_values` → `array`.
- Constante `SYSTEM_KEYS`: `entry_date`, `lot`, `recorded_at`, `intake_lbs`, `applied_raw_lbs`, `trimmed_lbs`, `overripe_lbs`, `recovery_pct`, `overripe_pct`, `grn_balance`, `observations`.
- Relaciones: `task()` → `WeeklyPlanTask` (`weekly_plan_task_id`), `user()` → `User`.

Nueva relación: `WeeklyPlanTask::lotRecords()` → `hasMany(WeeklyPlanTaskLotRecord::class)`.

### `App\Calculators\LotCalculator`

```php
/**
 * @param  array<string, mixed>  $values  captured system values of the record
 * @param  array<int, string>  $assignedKeys  keys assigned to the line
 * @return array{recovery_pct: ?float, overripe_pct: ?float, grn_balance: ?float}
 */
public function calculate(array $values, array $assignedKeys): array
```

| Campo | Fórmula | Se calcula si… | Si no |
| ----- | ------- | -------------- | ----- |
| `recovery_pct` | `trimmed_lbs / applied_raw_lbs × 100` | asignado, `trimmed_lbs` no null y `applied_raw_lbs` > 0 | `null` |
| `overripe_pct` | `overripe_lbs / applied_raw_lbs × 100` | asignado, `overripe_lbs` no null y `applied_raw_lbs` > 0 | `null` |
| `grn_balance` | `intake_lbs − applied_raw_lbs` | asignado y ambos no null | `null` |

Los porcentajes se guardan en escala 0–100 con precisión completa; el Resource redondea a 2 decimales. Mismo criterio que `PalletCalculator`: un calculado no asignado a la línea siempre queda en `null`.

### Lógica compartida que se extrae

**`CaptureType::unitLabel(): string`** devuelve `tarima`, `lote` o `producto`.

**`CaptureValueRules::forTask(?WeeklyPlanTask $task, CaptureType $captureType, bool $isUpdate): array`**

- Devuelve `['rules' => [], 'attributes' => []]` si la tarea es null, si su línea no es de `$captureType` o si no tiene campos. En esos casos el Service responde 404 o 400.
- Si no, delega en `forLineFields($line->lineFields, $isUpdate)`.
- Espera la tarea con `performance.line.lineFields.captureField` cargado.

`Create…` y `Update…PerformanceRecordRequest` reemplazan su `palletLineFields()` privado por `forTask(..., CaptureType::Pallet, ...)`. Los Requests de lote usan `CaptureType::Lot`.

**`WeeklyPlanTasksServiceInterface::getCaptureLineFields(WeeklyPlanTask $task, CaptureType $captureType): Collection`**

- La línea de la tarea no es de `$captureType` → `BadRequestError("La línea de la tarea no captura por {$captureType->unitLabel()}")`.
- La línea no tiene `line_fields` → `BadRequestError('La línea no tiene campos de captura configurados')`.
- Si no, devuelve los `line_fields` con `captureField`.

`WeeklyPlanTaskPerformanceRecordsService` reemplaza su `getPalletLineFields()` privado por esta llamada. Los mensajes de `pallet` no cambian (`… no captura por tarima`).

### Endpoints

`apiResource('/weekly-plan-task-lot-records')` con `parameters(['weekly-plan-task-lot-records' => 'id'])`, en el grupo `jwt.auth` de `routes/weeklyplantasks.php`, junto a las tomas de rendimiento.

| Método   | Ruta | Body / query | Respuesta |
| -------- | ---- | ------------ | --------- |
| `GET`    | `/weekly-plan-task-lot-records` | `?weeklyPlanTaskId=` (obligatorio) | 200, `WeeklyPlanTaskLotRecordResource::collection` |
| `GET`    | `/weekly-plan-task-lot-records/{id}` | — | 200, `WeeklyPlanTaskLotRecordResource` |
| `POST`   | `/weekly-plan-task-lot-records` | `weekly_plan_task_id`, `values` | 201, `WeeklyPlanTaskLotRecordResource` del creado |
| `PATCH` / `PUT` | `/weekly-plan-task-lot-records/{id}` | `values` | 200, `data: null` |
| `DELETE` | `/weekly-plan-task-lot-records/{id}` | — | 200, `data: null` |

Mensajes de éxito:

- Listado: `Registros de Lote Obtenidos Correctamente`.
- Detalle: `Registro de Lote Obtenido Correctamente`.
- Crear: `Registro de Lote Creado Correctamente`.
- Editar: `Registro de Lote Actualizado Correctamente`.
- Eliminar: `Registro de Lote Eliminado Correctamente`.

Ejemplo:

```json
POST /weekly-plan-task-lot-records
{
  "weekly_plan_task_id": 210,
  "values": {
    "entry_date": "2026-10-06",
    "lot": "LX2 - 6791",
    "recorded_at": "15:00",
    "intake_lbs": 22803.5,
    "applied_raw_lbs": 18064.5,
    "trimmed_lbs": 8127,
    "overripe_lbs": 47
  }
}
```

### Requests

`CreateWeeklyPlanTaskLotRecordRequest`:

```text
weekly_plan_task_id  integer  required|exists:weekly_plan_tasks,id
values               array    required
values.{key}         dinámico (CaptureValueRules::forTask, CaptureType::Lot, isUpdate = false)
```

`UpdateWeeklyPlanTaskLotRecordRequest`:

```text
values               array    required
values.{key}         dinámico (CaptureValueRules::forTask, CaptureType::Lot, isUpdate = true)
```

El Update resuelve la tarea desde el registro (`route('id')`). Los mensajes de `weekly_plan_task_id` y `values` son los mismos que en las tomas de rendimiento, más `CaptureValueRules::messages()`.

### Reglas de negocio — `WeeklyPlanTaskLotRecordsService`

Depende de `WeeklyPlanTasksServiceInterface` (`getWeeklyPlanTaskById`, `getCaptureLineFields`) y de `LotCalculator`.

- Listado sin `weeklyPlanTaskId` → `BadRequestError('El id de la tarea del plan semanal es obligatorio')`. Se ordena por `created_at` con `with('user')`.
- Registro inexistente → `NotFoundError('El registro de lote no existe')`.
- Escribir (crear, editar, eliminar) con la tarea fuera de status `4` → `BadRequestError('Solo se pueden registrar lotes en una tarea en progreso')`.
- Crear y editar llaman a `getCaptureLineFields($task, CaptureType::Lot)`.
- Lote repetido en la misma tarea (ignorando el propio registro al editar) → `BadRequestError('El lote {lot} ya fue registrado en la tarea')`. Sin lote no se restringe.
- Con los valores finales (en update, guardados + recibidos):
  - `applied_raw_lbs` > `intake_lbs` (ambos no null) → `BadRequestError('La MP aplicada no puede ser mayor a las libras al ingreso')`.
  - `trimmed_lbs + overripe_lbs` > `applied_raw_lbs` (con `applied_raw_lbs` no null y al menos uno de los otros dos no null; un null cuenta como 0) → `BadRequestError('Las libras recortadas y sobremaduras no pueden superar la MP aplicada')`.

**Crear:**

1. Separa `values`: keys en `SYSTEM_KEYS` → columnas; resto → `extra_values` (`null` si queda vacío).
2. Aplica las validaciones de negocio.
3. `LotCalculator::calculate(columnas, keys asignadas)`.
4. Crea con `user_id = auth()->user()->id`.

**Editar:** mezcla los valores guardados con los recibidos (`null` explícito borra), valida, recalcula con la configuración actual y guarda. Mismo comportamiento que las tomas de rendimiento.

**Eliminar:** borrado físico.

### `WeeklyPlanTaskLotRecordResource`

```php
[
    'id' => $this->id,
    'weekly_plan_task_id' => $this->weekly_plan_task_id,
    'entry_date' => $this->entry_date?->format('Y-m-d'),
    'lot' => $this->lot,
    'recorded_at' => $this->recorded_at ? substr($this->recorded_at, 0, 5) : null,
    'intake_lbs' => $this->roundOrNull($this->intake_lbs),
    'applied_raw_lbs' => $this->roundOrNull($this->applied_raw_lbs),
    'trimmed_lbs' => $this->roundOrNull($this->trimmed_lbs),
    'overripe_lbs' => $this->roundOrNull($this->overripe_lbs),
    'recovery_pct' => $this->roundOrNull($this->recovery_pct),
    'overripe_pct' => $this->roundOrNull($this->overripe_pct),
    'grn_balance' => $this->roundOrNull($this->grn_balance),
    'observations' => $this->observations,
    'extra_values' => (object) ($this->extra_values ?? []),
    'user_id' => $this->user_id,
    'user_name' => $this->user->name,
    'created_at' => $this->created_at->format('d-m-Y H:i'),
    'updated_at' => $this->updated_at->format('d-m-Y H:i'),
]
```

### Cambios en `WeeklyPlanTasks`

- `WeeklyPlanTasksService`: agrega `withSum('lotRecords', 'trimmed_lbs')` (o `loadSum`) junto al de `net_weight` en `getWeeklyPlanTasks`, `getWeeklyPlanTaskById`, `splitWeeklyPlanTask` y `transitionWeeklyPlanTask`.
- `WeeklyPlanTaskResource`: `recorded_pounds = round(performance_records_sum_net_weight + lot_records_sum_trimmed_lbs, 2)`, tratando null como 0.

Una tarea solo tiene registros de la familia de su línea (el cambio de familia está bloqueado con campos configurados), así que sumar ambas fuentes da el avance correcto sin consultar el `capture_type`.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Extraer lógica compartida.** `CaptureType::unitLabel()`, `CaptureValueRules::forTask()` y `getCaptureLineFields()` en la interface y el Service de `WeeklyPlanTasks`. Adaptar los Requests y el Service de tomas de rendimiento. Verificación: las tomas de rendimiento responden igual que antes (mismos 400 y 422).
2. **Migración y modelo.** `php artisan make:model WeeklyPlanTaskLotRecord -m --no-interaction`. Columnas, FKs, índice único, fillable, casts, `SYSTEM_KEYS` y relaciones; `lotRecords()` en `WeeklyPlanTask`. Verificación: `php artisan migrate` y `database-schema` muestra la tabla.
3. **`LotCalculator`.** Crear `app/Calculators/LotCalculator.php` con las 3 fórmulas.
4. **Esqueleto de la feature.** Interface, Service, Provider registrado en `bootstrap/providers.php`, Requests, Resource, Controller y `apiResource` en `routes/weeklyplantasks.php`. Verificación: `php artisan route:list --path=weekly-plan-task-lot-records` muestra las rutas.
5. **Captura.** Listado, detalle, crear, editar y eliminar con status `4`, familia, lote único, validaciones entre campos y cálculo.
6. **Avance de la tarea.** `withSum('lotRecords', 'trimmed_lbs')` en las cuatro consultas y `recorded_pounds` sumando ambas fuentes.
7. **Documentación.** Rutas, body `values`, schema `WeeklyPlanTaskLotRecord` y errores en `public/openapi.yaml`.
8. **Guía de integración para frontend.** Crear `references/weekly-plan-task-lot-records.md` con la estructura de `references/weekly-plan-task-performance-records.md`: resumen de endpoints, flujo (configurar la línea `lot` → formulario → registrar lotes), campos y calculados con sus fórmulas, reglas (status `4`, lote único, MP aplicada, recortadas + sobremaduro), `extra_values`, tipos TypeScript, ejemplos de request y respuesta, errores (400, 404, 422 con sus mensajes), cómo calcular la fila de totales en el frontend y checklist.
9. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [X] `php artisan migrate` crea `weekly_plan_task_lot_records` con FKs a `weekly_plan_tasks` (cascade) y `users`, y el único `(weekly_plan_task_id, lot)`.
- [X] `php artisan route:list --path=weekly-plan-task-lot-records` muestra las 5 acciones bajo `jwt.auth`.
- [X] Las tomas de rendimiento (`pallet`) siguen respondiendo con los mismos códigos y mensajes después de la extracción.
- [X] En una línea `lot` con todos los campos asignados, `POST` con `intake_lbs = 22803.5`, `applied_raw_lbs = 18064.5`, `trimmed_lbs = 8127` y `overripe_lbs = 47` guarda `recovery_pct ≈ 44.99`, `overripe_pct ≈ 0.26` y `grn_balance = 4739`. El Resource los devuelve redondeados a 2 decimales.
- [X] Un calculado no asignado a la línea queda en `null` aunque tenga entradas.
- [X] Con `applied_raw_lbs = 0` o vacía, `recovery_pct` y `overripe_pct` quedan en `null` (sin división por cero).
- [X] Un campo custom asignado se guarda en `extra_values` y se devuelve en el Resource.
- [X] Campo obligatorio ausente, tipo incorrecto (`entry_date = "06/10/2026"`), key no asignada o key calculada → 422 con los mensajes de `CaptureValueRules`.
- [X] Tarea cuya línea es `pallet` o `product` → 400 `La línea de la tarea no captura por lote`.
- [X] Línea `lot` sin `line_fields` → 400 `La línea no tiene campos de captura configurados`.
- [X] Escribir con la tarea fuera de status `4` → 400 `Solo se pueden registrar lotes en una tarea en progreso`.
- [X] Mismo `lot` dos veces en una tarea → 400 `El lote {lot} ya fue registrado en la tarea`. Editar un registro conservando su propio lote funciona.
- [X] `applied_raw_lbs` mayor que `intake_lbs` → 400 `La MP aplicada no puede ser mayor a las libras al ingreso`.
- [X] `trimmed_lbs + overripe_lbs` mayor que `applied_raw_lbs` → 400 `Las libras recortadas y sobremaduras no pueden superar la MP aplicada`.
- [X] `PATCH` con solo `values.trimmed_lbs` aplica las validaciones con los valores guardados y recalcula `recovery_pct` sin borrar los demás.
- [X] `GET /weekly-plan-task-lot-records` sin `weeklyPlanTaskId` → 400; con él devuelve los registros ordenados por `created_at` sin N+1.
- [X] Id inexistente en `show`, `PATCH` o `DELETE` → 404 `El registro de lote no existe`.
- [X] `recorded_pounds` de una tarea `lot` es la suma de `trimmed_lbs`, y el de una tarea `pallet` sigue siendo la suma de `net_weight`, en `index`, `show`, `splitTask`, `start` y `end`.
- [X] Borrar una `WeeklyPlanTask` borra sus registros de lote.
- [X] `public/openapi.yaml` documenta las rutas nuevas, el schema y los errores.
- [X] `references/weekly-plan-task-lot-records.md` existe y cubre los 5 endpoints, las fórmulas, los mensajes de error y el cálculo de totales.
- [X] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** feature y tabla propias (`WeeklyPlanTaskLotRecords`). Una fila de lote no tiene nada en común con una tarima; mismo patrón que las tomas de rendimiento.
- **No:** tabla genérica para `lot` y `product`. Mezclaría columnas de conceptos distintos, casi todas en null.
- **Sí:** rutas en `routes/weeklyplantasks.php`. Es un sub-recurso de la tarea, como las tomas de rendimiento.
- **Sí:** porcentajes en escala 0–100. El frontend los muestra sin convertir.
- **Sí:** precisión completa en BD y 2 decimales en el Resource. Mismo criterio que `duration_hours` (SPEC 07).
- **Sí:** `recorded_pounds` suma `trimmed_lbs` en líneas `lot`. Un solo campo de avance para el frontend, cualquiera sea la familia.
- **Sí:** sumar ambas fuentes sin mirar `capture_type`. Una tarea solo tiene registros de su familia; evita cargar la línea en `show`.
- **Sí:** lote único por tarea. Evita capturar dos veces el mismo GRN; mismo criterio que `pallet_number`.
- **Sí:** MP aplicada ≤ libras al ingreso. Evita un saldo GRN negativo.
- **Sí:** recortadas + sobremaduro ≤ MP aplicada. Evita más de 100% entre recuperación y sobremadurez.
- **Sí:** reglas entre campos como 400 en el Service. Dependen de valores guardados (en update) y de la configuración; no son validación de formato.
- **Sí:** extraer `forTask` y `getCaptureLineFields`, y adaptar `pallet`. Evita tener la lógica triplicada cuando llegue `product`.
- **No:** extraer la separación columnas / `extra_values`. Son dos líneas de `Arr::only` / `Arr::except`.
- **Sí:** `getCaptureLineFields` en `WeeklyPlanTasksService`. Los Services de captura ya dependen de esa interface; no hace falta una carpeta nueva.
- **No:** totales por tarea en el backend. El frontend los calcula con el listado; se agregan en otra spec si hacen falta para reportes.
- **No:** saldo GRN entre tareas. Requiere inventario de materia prima por GRN.
- **No:** tests automatizados. Consistente con SPEC 01–09; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| La extracción cambia el comportamiento de las tomas de rendimiento. | El paso 1 se verifica contra los mismos 400 y 422 de la SPEC 09 antes de seguir. |
| Las líneas de recorte siguen como `pallet` (default de SPEC 08). | Cambiar su `capture_type` a `lot` antes de configurarles campos. |
| Un mismo GRN se procesa legítimamente en dos tandas dentro de una tarea. | Se captura en una fila con el total aplicado. Si se vuelve frecuente, se revisa la unicidad en otra spec. |
| `recorded_pounds` compara libras recortadas contra `planned_pounds` (`boxes × presentation`), que en recorte puede no significar lo mismo. | Se acepta; el frontend decide cómo mostrarlo. Se revisa con datos reales. |
| Faltan los `withSum` de `lotRecords` en alguna consulta. | `recorded_pounds` cae al valor de `pallet` por el `?? 0`. Los criterios de aceptación revisan los 5 endpoints. |
| Se quita un campo custom de la línea con registros que tienen valor en `extra_values`. | El valor se conserva y se sigue devolviendo; el frontend no lo muestra. |

---

## Lo que **no** está en esta spec

- Captura de `product`.
- Saldo o inventario GRN entre tareas.
- Validar el GRN contra recepciones.
- Totales por tarea en el backend.
- Recalcular registros guardados.
- Módulos extra de recorte (tanque/prensa, tiempos muertos por área).
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
