# SPEC 09 — Captura por tarima con campos configurables (familia `pallet`)

> **Estado:** Implementado
> **Depende de:** SPEC 08
> **Fecha:** 2026-10-09
> **Objetivo:** Convertir las tomas de rendimiento (`weekly_plan_task_performance_records`) en la captura por tarima de la familia `pallet`, validada contra los `line_fields` de la línea y con los campos calculados resueltos por `PalletCalculator`.

---

## Por qué existe esta spec

La SPEC 08 dejó configurado **qué campos** usa cada línea, pero la captura sigue siendo la de las tomas de rendimiento: `pallet_number`, `boxes` y `weighed_pounds` fijos para todas las líneas.

Esta spec conecta ambas cosas para las líneas `pallet`. El registro de cada tarima recibe los valores de los campos que la línea tiene asignados, los valida según el catálogo y calcula `net_weight`, `ticket_weight` y `difference`.

Del análisis de los Excel:

- **Peso boleta** = `cajas × presentación del SKU`. Ejemplos: R5A `=cajas*18`, Empaque Frescos 48 × 24 = 1152, Jugos 91 × 20 = 1820. Es lo mismo que hoy guarda `theoretical_pounds`, así que pasa a ser **calculado**.
- **Peso neto** = báscula − tara.
- **Diferencial** = neto − boleta. Es la convención de Reempaque, IQF y Jugos, y la de `difference_pounds` actual.
- IQF no usa boleta ni diferencial: simplemente no los asigna.

Flujo del usuario:

1. La línea es `pallet` y tiene campos configurados (SPEC 08).
2. El frontend pide `GET /lines/{code}/fields` y arma el formulario.
3. Por cada tarima manda `POST /weekly-plan-task-performance-records` con `values: { key: valor }`.
4. El backend valida contra la configuración, separa los campos de sistema (columnas) de los custom (`extra_values`), calcula los asignados y guarda.

---

## Alcance

**Dentro:**

- `ticket_weight` pasa a calculado (`depends_on: ['boxes']`) en `CaptureFieldsSeeder`.
- Migración de `weekly_plan_task_performance_records`:
  - Renombra `weighed_pounds` → `net_weight`, `theoretical_pounds` → `ticket_weight` y `difference_pounds` → `difference`, todas nullable.
  - Agrega `lot`, `recorded_at`, `liters`, `status`, `scale_weight`, `tare`, `observations` y `extra_values`.
- `App\Calculators\PalletCalculator` (carpeta nueva).
- `App\Http\Requests\shared\CaptureValueRules`: reglas de validación dinámicas a partir de los `line_fields`, reutilizable por las SPEC 10 y 11.
- `CreateWeeklyPlanTaskPerformanceRecordRequest` y `UpdateWeeklyPlanTaskPerformanceRecordRequest` con body `values`.
- `WeeklyPlanTaskPerformanceRecordsService` con validaciones de familia y configuración, separación columnas / `extra_values` y cálculo.
- `WeeklyPlanTaskPerformanceRecordResource` con todas las columnas más `extra_values`.
- `recorded_pounds` de la tarea pasa a sumar `net_weight`.
- Actualización de `public/openapi.yaml`, `references/weekly-plan-task-performance-records.md` y `references/line-capture-fields.md`.

**Fuera de alcance (para specs futuras):**

- Captura de las familias `lot` (SPEC 10) y `product` (SPEC 11).
- Tara calculada con constantes del SKU (tara por caja, tara de tarima).
- Recalcular registros guardados cuando cambia la configuración de la línea o la presentación del SKU.
- Compatibilidad con el body viejo (`weighed_pounds` plano). El frontend se actualiza junto con esta spec.
- Configuración por default para líneas sin `line_fields`.
- Usar las tarimas para llenar `produced_boxes` / `weighed_pounds` al finalizar la tarea.
- Módulos extra (HPP, tanque/prensa).
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Cambio en `CaptureFieldsSeeder`

```php
$this->field(CaptureType::Pallet, 'ticket_weight', 'Peso boleta', CaptureFieldDataType::Number, ['boxes']),
```

Al tener `depends_on`, el seeder lo marca `is_calculated = true`. Después del `updateOrCreate`, el seeder pasa a `is_required = false` cualquier `line_field` de un campo calculado, porque una línea pudo asignar `ticket_weight` como obligatorio cuando todavía era de entrada.

### Migración: `update_weekly_plan_task_performance_records_for_pallet_capture`

```php
Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
    $table->renameColumn('weighed_pounds', 'net_weight');
    $table->renameColumn('theoretical_pounds', 'ticket_weight');
    $table->renameColumn('difference_pounds', 'difference');
});

Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
    $table->float('net_weight')->nullable()->change();
    $table->float('ticket_weight')->nullable()->default(null)->change();
    $table->float('difference')->nullable()->default(null)->change();

    $table->string('lot', 50)->nullable()->after('pallet_number');
    $table->time('recorded_at')->nullable()->after('lot');
    $table->float('liters')->nullable()->after('boxes');
    $table->string('status', 20)->nullable()->after('liters');
    $table->float('scale_weight')->nullable()->after('status');
    $table->float('tare')->nullable()->after('scale_weight');
    $table->string('observations', 500)->nullable();
    $table->json('extra_values')->nullable();
});

DB::table('weekly_plan_task_performance_records')
    ->whereNull('boxes')
    ->update(['ticket_weight' => null, 'difference' => null]);
```

El último paso corrige los registros viejos sin cajas, que tenían `0` en teórico y diferencia por no poder calcularlos. Los registros con cajas conservan sus valores.

| Columna         | key del catálogo | Tipo | Origen |
| --------------- | ---------------- | ---- | ------ |
| `pallet_number` | `pallet_number`  | integer | captura |
| `lot`           | `lot`            | text | captura |
| `recorded_at`   | `recorded_at`    | time (`H:i`) | captura |
| `boxes`         | `boxes`          | integer | captura |
| `liters`        | `liters`         | number | captura |
| `status`        | `status`         | select | captura |
| `scale_weight`  | `scale_weight`   | number | captura |
| `tare`          | `tare`           | number | captura |
| `net_weight`    | `net_weight`     | number | `PalletCalculator` |
| `ticket_weight` | `ticket_weight`  | number | `PalletCalculator` |
| `difference`    | `difference`     | number | `PalletCalculator` |
| `observations`  | `observations`   | text | captura |
| `extra_values`  | campos custom    | JSON `{ key: valor }` | captura |

### Modelo `WeeklyPlanTaskPerformanceRecord`

- Fillable: `weekly_plan_task_id`, `user_id`, `pallet_number`, `lot`, `recorded_at`, `boxes`, `liters`, `status`, `scale_weight`, `tare`, `net_weight`, `ticket_weight`, `difference`, `observations`, `extra_values`.
- Casts: `extra_values` → `array`.
- Constante `SYSTEM_KEYS`: las 12 keys que tienen columna (todas las de la tabla anterior menos `extra_values`).

### `App\Calculators\PalletCalculator`

```php
/**
 * @param  array<string, mixed>  $values  captured system values of the record
 * @param  array<int, string>  $assignedKeys  keys assigned to the line
 * @return array{net_weight: ?float, ticket_weight: ?float, difference: ?float}
 */
public function calculate(array $values, ?float $presentation, array $assignedKeys): array
```

| Campo | Fórmula | Se calcula si… | Si no |
| ----- | ------- | -------------- | ----- |
| `net_weight` | `scale_weight − tare` | `net_weight` está asignado y `scale_weight` y `tare` no son null | `null` |
| `ticket_weight` | `boxes × presentation` | `ticket_weight` está asignado, `boxes` no es null y `presentation` > 0 | `null` |
| `difference` | `net_weight − ticket_weight` | `difference` está asignado y los dos calculados anteriores no son null | `null` |

Positivo en `difference` = sobra peso. Un calculado no asignado a la línea siempre queda en `null`, aunque tenga entradas.

### `App\Http\Requests\shared\CaptureValueRules`

```php
/**
 * @param  Collection<int, LineField>  $lineFields  with captureField loaded
 * @return array{rules: array<string, array<mixed>>, attributes: array<string, string>}
 */
public static function forLineFields(Collection $lineFields, bool $isUpdate): array
```

Arma las reglas de `values.{key}` para cada campo **de entrada** asignado:

| `data_type` | Regla |
| ----------- | ----- |
| `number`    | `numeric` |
| `integer`   | `integer` |
| `text`      | `string`, `max:500` |
| `date`      | `date_format:Y-m-d` |
| `time`      | `date_format:H:i` |
| `boolean`   | `boolean` |
| `select`    | `Rule::in(options)` |

- Prefijo: `required` si `is_required`, si no `nullable`. En update se antepone `sometimes`.
- Campos de sistema `number` / `integer`: además `min:0`. `pallet_number`: `min:1`. `lot`: `max:50`.
- Campos custom: solo la regla del tipo.
- `attributes`: `values.{key}` → etiqueta de la línea (o la del catálogo), para que los mensajes digan "El campo Tara es obligatorio".
- Regla closure sobre `values`:
  - key no asignada → `El campo {key} no está configurado para la línea`.
  - key de un calculado → `El campo {label} es calculado y no se puede enviar`.

Mensajes en español por regla (`required`, `numeric`, `integer`, `string`, `max`, `min`, `date_format`, `boolean`, `in`) usando `:attribute`.

### Requests

`CreateWeeklyPlanTaskPerformanceRecordRequest`:

```text
weekly_plan_task_id  integer  required|exists:weekly_plan_tasks,id
values               array    required
values.{key}         dinámico (CaptureValueRules, isUpdate = false)
```

`UpdateWeeklyPlanTaskPerformanceRecordRequest`:

```text
values               array    required
values.{key}         dinámico (CaptureValueRules, isUpdate = true)
```

Cada Request resuelve la línea en `rules()`: Create desde `weekly_plan_task_id` y Update desde el registro (`route('id')`), en ambos casos vía `task → performance → line → lineFields.captureField`. Si la tarea o el registro no existen, o la línea no es `pallet`, o no tiene campos, devuelve solo las reglas base. El Service responde el 404 o el 400 correspondiente.

Las reglas dinámicas viven en el FormRequest porque una `ValidationException` lanzada en el Service cae en el `catch` del Controller y saldría como 500.

### Reglas de negocio — `WeeklyPlanTaskPerformanceRecordsService`

Se mantienen: `NotFoundError` de tarea y registro, status `4` para escribir y `pallet_number` único por tarea.

Nuevas, en create y update antes de guardar:

- La línea de la tarea no es `pallet` → `BadRequestError('La línea de la tarea no captura por tarima')`.
- La línea no tiene `line_fields` → `BadRequestError('La línea no tiene campos de captura configurados')`.

**Crear:**

1. Separa `values`: keys en `SYSTEM_KEYS` → columnas; resto → `extra_values` (`null` si queda vacío).
2. `PalletCalculator::calculate(columnas, sku.presentation, keys asignadas)`.
3. Crea el registro con `user_id = auth()->user()->id`.

**Editar:**

1. Mezcla los valores guardados con los recibidos. Una key con `null` explícito borra el valor; en `extra_values` se guarda `null`.
2. Recalcula con la configuración **actual** de la línea y guarda.

**Eliminar:** sin cambios.

### `WeeklyPlanTaskPerformanceRecordResource`

```php
[
    'id' => $this->id,
    'weekly_plan_task_id' => $this->weekly_plan_task_id,
    'pallet_number' => $this->pallet_number,
    'lot' => $this->lot,
    'recorded_at' => $this->recorded_at ? substr($this->recorded_at, 0, 5) : null,
    'boxes' => $this->boxes,
    'liters' => $this->liters,
    'status' => $this->status,
    'scale_weight' => $this->roundOrNull($this->scale_weight),
    'tare' => $this->roundOrNull($this->tare),
    'net_weight' => $this->roundOrNull($this->net_weight),
    'ticket_weight' => $this->roundOrNull($this->ticket_weight),
    'difference' => $this->roundOrNull($this->difference),
    'observations' => $this->observations,
    'extra_values' => (object) ($this->extra_values ?? []),
    'user_id' => $this->user_id,
    'user_name' => $this->user->name,
    'created_at' => $this->created_at->format('d-m-Y H:i'),
    'updated_at' => $this->updated_at->format('d-m-Y H:i'),
]
```

`roundOrNull` redondea a 2 decimales o devuelve `null`. Las keys son siempre las mismas; el frontend decide qué columnas mostrar con `GET /lines/{code}/fields`.

### Cambios en `WeeklyPlanTasks`

- `WeeklyPlanTasksService`: `withSum('performanceRecords', 'weighed_pounds')` / `loadSum(...)` → `'net_weight'` en `getWeeklyPlanTasks`, `getWeeklyPlanTaskById`, `splitWeeklyPlanTask` y `transitionWeeklyPlanTask`.
- `WeeklyPlanTaskResource`: `recorded_pounds` lee `performance_records_sum_net_weight`. La key `recorded_pounds` no cambia.
- `weekly_plan_tasks.weighed_pounds` (peso al finalizar la tarea) no se toca.

### Endpoints

Las rutas no cambian (`apiResource('/weekly-plan-task-performance-records')`). Cambian el body y la respuesta:

```json
POST /weekly-plan-task-performance-records
{
  "weekly_plan_task_id": 120,
  "values": {
    "pallet_number": 1,
    "lot": "PA-26279-R5A",
    "recorded_at": "07:25",
    "boxes": 130,
    "scale_weight": 2534,
    "tare": 180,
    "temperatura": 4
  }
}
```

Los mensajes de éxito no cambian.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **`ticket_weight` calculado.** Cambio en `CaptureFieldsSeeder` y corrección de `is_required` en `line_fields` de calculados. Verificación: correr el seeder deja `ticket_weight` con `is_calculated = true` y `depends_on = ["boxes"]`, y sigue habiendo 30 filas.
2. **Migración y renombre.** Migración de columnas y corrección de los registros viejos sin cajas. Actualizar fillable y casts del modelo, y cambiar `weighed_pounds` / `theoretical_pounds` / `difference_pounds` por los nombres nuevos en el Service, el Resource de registros, `WeeklyPlanTasksService` y `WeeklyPlanTaskResource`, conservando el body viejo hasta el paso 5. Verificación: `php artisan migrate` sin error; `GET /weekly-plan-tasks/{id}` sigue devolviendo `recorded_pounds`.
3. **`PalletCalculator`.** Crear `app/Calculators/PalletCalculator.php` con las 3 fórmulas y la regla de solo asignados.
4. **`CaptureValueRules`.** Crear `app/Http/Requests/shared/CaptureValueRules.php` con reglas por tipo, reglas extra de sistema, atributos y closure de keys no permitidas.
5. **Requests con `values`.** Reescribir Create y Update para resolver la línea y usar `CaptureValueRules`.
6. **Service.** Validación de familia y de configuración, separación columnas / `extra_values`, merge en update y uso de `PalletCalculator`.
7. **Resource.** Todas las columnas, `extra_values` y redondeo con null.
8. **Documentación.** Body, schema y errores nuevos de `/weekly-plan-task-performance-records` en `public/openapi.yaml`.
9. **Guías de integración.**
   - Reescribir `references/weekly-plan-task-performance-records.md` con el flujo nuevo: configuración de la línea, body `values`, campos calculados, `extra_values`, errores (400, 404, 422 con sus mensajes), tipos TypeScript y checklist.
   - En `references/line-capture-fields.md`, marcar `ticket_weight` como calculado.
10. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [X] `php artisan migrate` renombra las 3 columnas, las deja nullable y agrega `lot`, `recorded_at`, `liters`, `status`, `scale_weight`, `tare`, `observations` y `extra_values`.
- [X] Los registros existentes conservan su peso en `net_weight`; los que no tenían `boxes` quedan con `ticket_weight` y `difference` en `null`.
- [X] Después del seeder, `ticket_weight` es calculado con `depends_on = ["boxes"]` y ningún `line_field` de un calculado tiene `is_required = true`.
- [X] En una línea con `pallet_number`, `boxes`, `scale_weight`, `tare`, `net_weight`, `ticket_weight` y `difference` asignados y SKU con `presentation = 18`, `POST` con `boxes = 130`, `scale_weight = 2534` y `tare = 180` guarda `net_weight = 2354`, `ticket_weight = 2340` y `difference = 14`.
- [X] En una línea sin `ticket_weight` ni `difference` asignados (caso IQF), ambos quedan en `null` aunque se manden `boxes`.
- [X] Con `tare` vacía (no obligatoria), `net_weight` y `difference` quedan en `null`.
- [X] Con SKU sin `presentation`, `ticket_weight` y `difference` quedan en `null`.
- [X] Un campo custom asignado (ej. `temperatura`) se guarda en `extra_values` y se devuelve en `extra_values.temperatura`.
- [X] Un campo obligatorio ausente → 422 con `errors["values.{key}"]` y la etiqueta de la línea en el mensaje.
- [X] Un valor con el tipo incorrecto (texto en `tare`, `recorded_at = "7:5"`, `status = "OTRO"`) → 422 con mensaje en español.
- [X] Una key no asignada a la línea → 422 `El campo {key} no está configurado para la línea`.
- [X] Una key calculada (ej. `net_weight`) → 422 `El campo Peso neto es calculado y no se puede enviar`.
- [X] Tarea cuya línea es `lot` o `product` → 400 `La línea de la tarea no captura por tarima`.
- [X] Línea `pallet` sin `line_fields` → 400 `La línea no tiene campos de captura configurados`.
- [X] `PATCH` con solo `values.tare` recalcula `net_weight` y `difference` sin borrar los otros valores; `values.lot: null` borra el lote.
- [X] `pallet_number` repetido en la misma tarea → 400, igual que antes.
- [X] Escribir con la tarea fuera de status `4` → 400, igual que antes.
- [X] El Resource devuelve siempre las 12 keys de sistema (con `null` cuando no aplica) y `extra_values` como objeto (`{}` si no hay).
- [X] `recorded_pounds` de la tarea es la suma de `net_weight` en `index`, `show`, `splitTask`, `start` y `end`.
- [X] `public/openapi.yaml` documenta el body `values`, el schema nuevo del registro y los errores 400 y 422 nuevos.
- [X] `references/weekly-plan-task-performance-records.md` describe el flujo nuevo; `references/line-capture-fields.md` marca `ticket_weight` como calculado.
- [X] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** `ticket_weight` calculado como `boxes × sku.presentation`. Coincide con las fórmulas de los Excel y con el `theoretical_pounds` actual.
- **No:** boleta capturada. Sería otro dato a escribir por tarima que el sistema ya puede calcular.
- **Sí:** tara capturada. La fórmula de R5A (`cajas × 1 + 50`) depende de constantes por SKU, que quedan fuera de alcance.
- **Sí:** `difference = net_weight − ticket_weight`. Positivo = sobra peso; es la convención de la mayoría de las hojas y del código actual.
- **Sí:** extender `weekly_plan_task_performance_records` y renombrar columnas a las keys del catálogo. Un nombre por concepto; el mapeo key → columna es directo.
- **No:** tabla nueva. Las tomas de rendimiento ya son la captura por tarima; duplicarlas obligaría a migrar o mantener dos flujos.
- **No:** mantener los nombres viejos. Dos nombres para el mismo dato confunden en código y en reportes.
- **Sí:** romper el contrato del endpoint (body `values`, campos renombrados). El frontend se actualiza junto con esta spec.
- **Sí:** body con objeto `values`. Sistema y custom van por key sin mezclarse con `weekly_plan_task_id`.
- **Sí:** 422 para keys no asignadas o calculadas. Detecta errores del frontend en lugar de descartar datos en silencio.
- **Sí:** 400 si la línea no tiene campos configurados. Obliga a configurar cada línea antes de capturar.
- **No:** configuración por default para líneas sin `line_fields`. Ocultaría líneas sin configurar.
- **Sí:** calcular solo los calculados asignados a la línea. La configuración manda; no se guardan valores que nadie pidió.
- **Sí:** `null` cuando faltan entradas. "No se pudo calcular" no es `0`; `withSum` trata `null` como `0`.
- **Sí:** guardar los calculados al escribir (no calcular al leer). Mismo patrón que `theoretical_pounds` y `duration_hours`; permite `withSum`.
- **Sí:** validación dinámica en el FormRequest. Laravel responde 422 estándar; desde el Service saldría 500.
- **Sí:** `CaptureValueRules` en `app/Http/Requests/shared/`. Lo reutilizan las SPEC 10 y 11.
- **Sí:** `min:0` en campos de sistema numéricos y `min:1` en `pallet_number`. Los custom solo validan tipo (una temperatura puede ser negativa).
- **Sí:** `app/Calculators/` como carpeta nueva. Calculan valores, no orquestan; recibirá `LotCalculator` y `ProductCalculator`.
- **Sí:** editar recalcula con la configuración actual de la línea.
- **No:** recalcular registros viejos al cambiar la configuración o la presentación del SKU.
- **Sí:** Resource con todas las keys de sistema más `extra_values`. Tipos TypeScript estables; un registro viejo no pierde datos si se cambia la configuración.
- **Sí:** `recorded_pounds` suma `net_weight`. Es el mismo dato que antes se llamaba `weighed_pounds`.
- **No:** tests automatizados. Consistente con SPEC 01–08; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Al desplegar, todas las líneas `pallet` dejan de aceptar tomas hasta que se configuren. | Configurar `capture_type` y `line_fields` de las líneas activas antes del deploy (SPEC 08 ya está en producción). |
| El frontend sigue mandando el body viejo (`weighed_pounds`). | Responde 422 porque falta `values`. Se despliega junto con el frontend. |
| Una línea tenía `ticket_weight` asignado sin `boxes` (era de entrada en SPEC 08). | `ticket_weight` y `difference` quedan en `null`. Se corrige asignando `boxes`. |
| Editar un registro viejo (solo `net_weight`, sin báscula ni tara) recalcula `net_weight` a `null` si la línea lo tiene asignado. | Solo afecta a tareas en status `4` creadas antes del deploy. Se acepta; se corrige capturando báscula y tara. |
| Una línea asigna `scale_weight` y `tare` pero no `net_weight`. | `net_weight` queda en `null` y `recorded_pounds` no avanza. La guía de integración lo advierte. |
| Se quita un campo custom de la línea con registros que tienen valor en `extra_values`. | El valor se conserva y se sigue devolviendo; el frontend no lo muestra. |
| Cambiar `presentation` del SKU no actualiza registros guardados. | Se acepta, igual que hoy con `theoretical_pounds`. |

---

## Lo que **no** está en esta spec

- Captura de `lot` y `product`.
- Tara calculada con constantes del SKU.
- Recalcular registros guardados.
- Compatibilidad con el body viejo.
- Configuración por default para líneas sin campos.
- Llenar la producción de la tarea a partir de las tarimas.
- Módulos extra (HPP, tanque/prensa).
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
