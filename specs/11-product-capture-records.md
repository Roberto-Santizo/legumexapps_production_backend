# SPEC 11 — Captura por producto (familia `product`)

> **Estado:** Aprobado
> **Depende de:** SPEC 08, SPEC 09, SPEC 10
> **Fecha:** 2026-11-10
> **Objetivo:** Registrar, mientras la tarea está en progreso, una fila por variante de producto o materia prima en las líneas `product`, validada contra los `line_fields` de la línea y con `% recuperación` y `% rechazo` resueltos por `ProductCalculator`.

---

## Por qué existe esta spec

Las líneas `product` (Recorte Frescos, Máquinas, Destajos, Urshell recorte) ya se pueden configurar (SPEC 08), pero no tienen dónde capturar. Hoy los endpoints de tarima y de lote rechazan sus tareas con 400, así que esas líneas no pueden registrar nada.

En los Excel, cada fila de "1. PRODUCCIÓN LÍNEA" de estas líneas es una variante de producto o materia prima:

| Columna Excel | key | Origen |
| ------------- | --- | ------ |
| Materia prima | `raw_material` | captura |
| Libras materia prima | `raw_lbs` | captura |
| Libras clasificadas | `classified_lbs` | captura |
| Libras recortadas | `trimmed_lbs` | captura |
| Rechazo | `rejected_lbs` | captura |
| Empacado | `packed_lbs` | captura |
| % Recuperación | `recovery_pct` | `trimmed_lbs / raw_lbs` |
| % Rechazo | `rejection_pct` | `rejected_lbs / raw_lbs` |

Esta spec replica el flujo de la SPEC 10 para `product`. Reusa la lógica compartida que la SPEC 10 extrajo: `CaptureValueRules::forTask()`, `getCaptureLineFields()` y `CaptureType::unitLabel()`.

Flujo del usuario:

1. La línea es `product` y tiene campos configurados.
2. El frontend pide `GET /lines/{code}/fields` y arma el formulario.
3. Por cada variante manda `POST /weekly-plan-task-product-records` con `values: { key: valor }`.
4. El backend valida, separa columnas de `extra_values`, aplica las reglas entre campos, calcula y guarda.
5. El avance de la tarea (`recorded_pounds`) suma las libras recortadas.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_product_records`, modelo `WeeklyPlanTaskProductRecord` y relación `WeeklyPlanTask::productRecords()`.
- Feature `WeeklyPlanTaskProductRecords`: Interface, Service, Provider, Requests, Resource y Controller, con `apiResource('/weekly-plan-task-product-records')` en `routes/weeklyplantasks.php`.
- `App\Calculators\ProductCalculator`.
- Reglas de negocio entre campos:
  - clasificadas ≤ materia prima.
  - recortadas + rechazo ≤ materia prima.
  - empacado ≤ recortadas.
- `raw_material` como texto libre y repetible dentro de la tarea.
- `recorded_pounds` de la tarea = suma de `net_weight` (tarimas) + `trimmed_lbs` (lotes) + `trimmed_lbs` (productos).
- Documentación en `public/openapi.yaml` y guía `references/weekly-plan-task-product-records.md`.

**Fuera de alcance (para specs futuras):**

- Ligar `raw_material` al catálogo `raw_materials` (FK, select con búsqueda).
- Unicidad de `raw_material` por tarea.
- Totales por tarea (fila TOTALES del Excel). El frontend los calcula con el listado.
- Recalcular registros guardados cuando cambia la configuración de la línea.
- Cambiar el `capture_type` de las líneas en `lines.json` o en base de datos. Es configuración de datos, no código.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_product_records_table`

```php
Schema::create('weekly_plan_task_product_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('raw_material', 150)->nullable();
    $table->float('raw_lbs')->nullable();
    $table->float('classified_lbs')->nullable();
    $table->float('trimmed_lbs')->nullable();
    $table->float('rejected_lbs')->nullable();
    $table->float('packed_lbs')->nullable();
    $table->float('recovery_pct')->nullable();
    $table->float('rejection_pct')->nullable();
    $table->string('observations', 500)->nullable();
    $table->json('extra_values')->nullable();
    $table->timestamps();

    $table->index('weekly_plan_task_id');
});
```

Sin índice único: `raw_material` se puede repetir en la misma tarea.

### Modelo `App\Models\WeeklyPlanTaskProductRecord`

- Fillable: `weekly_plan_task_id`, `user_id`, `raw_material`, `raw_lbs`, `classified_lbs`, `trimmed_lbs`, `rejected_lbs`, `packed_lbs`, `recovery_pct`, `rejection_pct`, `observations`, `extra_values`.
- Casts: `extra_values` → `array`.
- Constante `SYSTEM_KEYS`: `raw_material`, `raw_lbs`, `classified_lbs`, `trimmed_lbs`, `rejected_lbs`, `packed_lbs`, `recovery_pct`, `rejection_pct`, `observations`.
- Relaciones: `task()` → `WeeklyPlanTask` (`weekly_plan_task_id`), `user()` → `User`.

Nueva relación: `WeeklyPlanTask::productRecords()` → `hasMany(WeeklyPlanTaskProductRecord::class)`.

### `App\Calculators\ProductCalculator`

```php
/**
 * @param  array<string, mixed>  $values  captured system values of the record
 * @param  array<int, string>  $assignedKeys  keys assigned to the line
 * @return array{recovery_pct: ?float, rejection_pct: ?float}
 */
public function calculate(array $values, array $assignedKeys): array
```

| Campo | Fórmula | Se calcula si… | Si no |
| ----- | ------- | -------------- | ----- |
| `recovery_pct` | `trimmed_lbs / raw_lbs × 100` | asignado, `trimmed_lbs` no null y `raw_lbs` > 0 | `null` |
| `rejection_pct` | `rejected_lbs / raw_lbs × 100` | asignado, `rejected_lbs` no null y `raw_lbs` > 0 | `null` |

Escala 0–100 con precisión completa en BD; el Resource redondea a 2 decimales. Mismo criterio que `LotCalculator`: un calculado no asignado a la línea siempre queda en `null`.

### Endpoints

`apiResource('/weekly-plan-task-product-records')` con `parameters(['weekly-plan-task-product-records' => 'id'])`, en el grupo `jwt.auth` de `routes/weeklyplantasks.php`, junto a las tomas de rendimiento y los registros de lote.

| Método   | Ruta | Body / query | Respuesta |
| -------- | ---- | ------------ | --------- |
| `GET`    | `/weekly-plan-task-product-records` | `?weeklyPlanTaskId=` (obligatorio) | 200, `WeeklyPlanTaskProductRecordResource::collection` |
| `GET`    | `/weekly-plan-task-product-records/{id}` | — | 200, `WeeklyPlanTaskProductRecordResource` |
| `POST`   | `/weekly-plan-task-product-records` | `weekly_plan_task_id`, `values` | 201, `WeeklyPlanTaskProductRecordResource` del creado |
| `PATCH` / `PUT` | `/weekly-plan-task-product-records/{id}` | `values` | 200, `data: null` |
| `DELETE` | `/weekly-plan-task-product-records/{id}` | — | 200, `data: null` |

Mensajes de éxito:

- Listado: `Registros de Producto Obtenidos Correctamente`.
- Detalle: `Registro de Producto Obtenido Correctamente`.
- Crear: `Registro de Producto Creado Correctamente`.
- Editar: `Registro de Producto Actualizado Correctamente`.
- Eliminar: `Registro de Producto Eliminado Correctamente`.

Ejemplo:

```json
POST /weekly-plan-task-product-records
{
  "weekly_plan_task_id": 315,
  "values": {
    "raw_material": "Brócoli",
    "raw_lbs": 1000,
    "classified_lbs": 950,
    "trimmed_lbs": 800,
    "rejected_lbs": 120,
    "packed_lbs": 780
  }
}
```

### Requests

`CreateWeeklyPlanTaskProductRecordRequest`:

```text
weekly_plan_task_id  integer  required|exists:weekly_plan_tasks,id
values               array    required
values.{key}         dinámico (CaptureValueRules::forTask, CaptureType::Product, isUpdate = false)
```

`UpdateWeeklyPlanTaskProductRecordRequest`:

```text
values               array    required
values.{key}         dinámico (CaptureValueRules::forTask, CaptureType::Product, isUpdate = true)
```

El Update resuelve la tarea desde el registro (`route('id')`). Los mensajes de `weekly_plan_task_id` y `values` son los mismos que en los registros de lote, más `CaptureValueRules::messages()`.

`CaptureValueRules::typeRules` limita la key de sistema `raw_material` a `string|max:150`, igual que `lot` a 50, para que coincida con la columna.

### Reglas de negocio — `WeeklyPlanTaskProductRecordsService`

Depende de `WeeklyPlanTasksServiceInterface` (`getWeeklyPlanTaskById`, `getCaptureLineFields`) y de `ProductCalculator`.

- Listado sin `weeklyPlanTaskId` → `BadRequestError('El id de la tarea del plan semanal es obligatorio')`. Se ordena por `created_at` con `with('user')`.
- Registro inexistente → `NotFoundError('El registro de producto no existe')`.
- Escribir (crear, editar, eliminar) con la tarea fuera de status `4` → `BadRequestError('Solo se pueden registrar productos en una tarea en progreso')`.
- Crear y editar llaman a `getCaptureLineFields($task, CaptureType::Product)`.
- Con los valores finales (en update, guardados + recibidos):
  - `classified_lbs` > `raw_lbs` (ambos no null) → `BadRequestError('Las libras clasificadas no pueden superar las libras de materia prima')`.
  - `trimmed_lbs + rejected_lbs` > `raw_lbs` (con `raw_lbs` no null y al menos uno de los otros dos no null; un null cuenta como 0) → `BadRequestError('Las libras recortadas y el rechazo no pueden superar las libras de materia prima')`.
  - `packed_lbs` > `trimmed_lbs` (ambos no null) → `BadRequestError('Lo empacado no puede superar las libras recortadas')`.

**Crear:**

1. Separa `values`: keys en `SYSTEM_KEYS` → columnas; resto → `extra_values` (`null` si queda vacío).
2. Aplica las validaciones de negocio.
3. `ProductCalculator::calculate(columnas, keys asignadas)`.
4. Crea con `user_id = auth()->user()->id`.

**Editar:** mezcla los valores guardados con los recibidos (`null` explícito borra), valida, recalcula con la configuración actual y guarda. Mismo comportamiento que los registros de lote.

**Eliminar:** borrado físico.

### `WeeklyPlanTaskProductRecordResource`

```php
[
    'id' => $this->id,
    'weekly_plan_task_id' => $this->weekly_plan_task_id,
    'raw_material' => $this->raw_material,
    'raw_lbs' => $this->roundOrNull($this->raw_lbs),
    'classified_lbs' => $this->roundOrNull($this->classified_lbs),
    'trimmed_lbs' => $this->roundOrNull($this->trimmed_lbs),
    'rejected_lbs' => $this->roundOrNull($this->rejected_lbs),
    'packed_lbs' => $this->roundOrNull($this->packed_lbs),
    'recovery_pct' => $this->roundOrNull($this->recovery_pct),
    'rejection_pct' => $this->roundOrNull($this->rejection_pct),
    'observations' => $this->observations,
    'extra_values' => (object) ($this->extra_values ?? []),
    'user_id' => $this->user_id,
    'user_name' => $this->user->name,
    'created_at' => $this->created_at->format('d-m-Y H:i'),
    'updated_at' => $this->updated_at->format('d-m-Y H:i'),
]
```

### Cambios en `WeeklyPlanTasks`

- `WeeklyPlanTasksService`: agrega `withSum('productRecords', 'trimmed_lbs')` (o `loadSum`) junto a los de `net_weight` y `lotRecords` en `getWeeklyPlanTasks`, `getWeeklyPlanTaskById`, `splitWeeklyPlanTask` y `transitionWeeklyPlanTask`.
- `WeeklyPlanTaskResource`: `recorded_pounds = round(performance_records_sum_net_weight + lot_records_sum_trimmed_lbs + product_records_sum_trimmed_lbs, 2)`, tratando null como 0.

Igual que en la SPEC 10, una tarea solo tiene registros de la familia de su línea, así que sumar las tres fuentes da el avance correcto sin consultar el `capture_type`.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración y modelo.** `php artisan make:model WeeklyPlanTaskProductRecord -m --no-interaction`. Columnas, FKs, índice, fillable, casts, `SYSTEM_KEYS` y relaciones; `productRecords()` en `WeeklyPlanTask`. Verificación: `php artisan migrate` y `database-schema` muestra la tabla.
2. **`ProductCalculator`.** Crear `app/Calculators/ProductCalculator.php` con las 2 fórmulas.
3. **Esqueleto de la feature.** Interface, Service, Provider registrado en `bootstrap/providers.php`, Requests, Resource, Controller y `apiResource` en `routes/weeklyplantasks.php`. Verificación: `php artisan route:list --path=weekly-plan-task-product-records` muestra las rutas.
4. **Captura.** Listado, detalle, crear, editar y eliminar con status `4`, familia, validaciones entre campos y cálculo.
5. **Avance de la tarea.** `withSum('productRecords', 'trimmed_lbs')` en las cuatro consultas y `recorded_pounds` sumando las tres fuentes.
6. **Documentación.** Rutas, body `values`, schema `WeeklyPlanTaskProductRecord` y errores en `public/openapi.yaml`.
7. **Guía de integración para frontend.** Crear `references/weekly-plan-task-product-records.md` con la estructura de `references/weekly-plan-task-lot-records.md`: resumen de endpoints, flujo (configurar la línea `product` → formulario → registrar productos), campos y calculados con sus fórmulas, reglas (status `4`, las tres reglas entre campos, `raw_material` repetible), `extra_values`, tipos TypeScript, ejemplos de request y respuesta, errores (400, 404, 422 con sus mensajes), cómo calcular la fila de totales en el frontend y checklist.
8. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `weekly_plan_task_product_records` con FKs a `weekly_plan_tasks` (cascade) y `users`.
- [ ] `php artisan route:list --path=weekly-plan-task-product-records` muestra las 5 acciones bajo `jwt.auth`.
- [ ] En una línea `product` con todos los campos asignados, `POST` con `raw_lbs = 1000`, `classified_lbs = 950`, `trimmed_lbs = 800`, `rejected_lbs = 120` y `packed_lbs = 780` guarda `recovery_pct = 80` y `rejection_pct = 12`.
- [ ] Un calculado no asignado a la línea queda en `null` aunque tenga entradas.
- [ ] Con `raw_lbs = 0` o vacía, `recovery_pct` y `rejection_pct` quedan en `null` (sin división por cero).
- [ ] Un campo custom asignado se guarda en `extra_values` y se devuelve en el Resource.
- [ ] Campo obligatorio ausente, tipo incorrecto (`raw_lbs = "mil"`), key no asignada o key calculada → 422 con los mensajes de `CaptureValueRules`.
- [ ] Tarea cuya línea es `pallet` o `lot` → 400 `La línea de la tarea no captura por producto`.
- [ ] Línea `product` sin `line_fields` → 400 `La línea no tiene campos de captura configurados`.
- [ ] Escribir con la tarea fuera de status `4` → 400 `Solo se pueden registrar productos en una tarea en progreso`.
- [ ] Dos registros con el mismo `raw_material` en una tarea se guardan sin error.
- [ ] `classified_lbs` mayor que `raw_lbs` → 400 `Las libras clasificadas no pueden superar las libras de materia prima`.
- [ ] `trimmed_lbs + rejected_lbs` mayor que `raw_lbs` → 400 `Las libras recortadas y el rechazo no pueden superar las libras de materia prima`.
- [ ] `packed_lbs` mayor que `trimmed_lbs` → 400 `Lo empacado no puede superar las libras recortadas`.
- [ ] `PATCH` con solo `values.trimmed_lbs` aplica las validaciones con los valores guardados y recalcula `recovery_pct` sin borrar los demás.
- [ ] `GET /weekly-plan-task-product-records` sin `weeklyPlanTaskId` → 400; con él devuelve los registros ordenados por `created_at` sin N+1.
- [ ] Id inexistente en `show`, `PATCH` o `DELETE` → 404 `El registro de producto no existe`.
- [ ] `recorded_pounds` de una tarea `product` es la suma de `trimmed_lbs`, y las tareas `pallet` y `lot` mantienen su valor, en `index`, `show`, `splitTask`, `start` y `end`.
- [ ] Los endpoints de tarima y lote siguen rechazando tareas `product` con su mismo mensaje.
- [ ] Borrar una `WeeklyPlanTask` borra sus registros de producto.
- [ ] `public/openapi.yaml` documenta las rutas nuevas, el schema y los errores.
- [ ] `references/weekly-plan-task-product-records.md` existe y cubre los 5 endpoints, las fórmulas, los mensajes de error y el cálculo de totales.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** feature y tabla propias (`WeeklyPlanTaskProductRecords`). Mismo patrón que `pallet` y `lot`; las columnas no se comparten.
- **No:** tabla genérica para `lot` y `product`. Ya descartada en la SPEC 10.
- **Sí:** reusar `CaptureValueRules::forTask()` y `getCaptureLineFields()` sin cambios. La SPEC 10 las extrajo para este caso.
- **Sí:** `raw_material` como texto libre. Respeta el campo de sistema `text` de la SPEC 08 y el formulario dinámico, igual que el GRN en `lot`.
- **No:** FK a `raw_materials`. Cambia el `data_type` del campo y obliga a un select con búsqueda; va en otra spec si hace falta.
- **Sí:** `raw_material` repetible en la tarea. Una misma materia prima puede llegar en varias tandas o variantes.
- **Sí:** `raw_material` máx. 150 en la validación. El campo `text` acepta 500 y la columna es de 150; sin el límite un texto largo daría 500 en vez de 422. Decidido durante la implementación.
- **No:** ampliar la columna a 500. Un nombre de materia prima no necesita tanto.
- **No:** índice único `(weekly_plan_task_id, raw_material)`. Con texto libre daría falsos distintos por mayúsculas o espacios.
- **Sí:** clasificadas ≤ materia prima. No se puede clasificar más de lo que entró.
- **Sí:** recortadas + rechazo ≤ materia prima. Evita más de 100% entre recuperación y rechazo.
- **Sí:** empacado ≤ recortadas. No se puede empacar más de lo recortado.
- **Sí:** reglas entre campos como 400 en el Service. Dependen de valores guardados (en update); no son validación de formato.
- **Sí:** `recorded_pounds` suma `trimmed_lbs` en líneas `product`. Mismo criterio que `lot`; las libras recortadas son la producción de la línea.
- **No:** sumar `packed_lbs` o `raw_lbs`. Rompería la coherencia con `lot`, que también es recorte.
- **Sí:** porcentajes en escala 0–100, precisión completa en BD y 2 decimales en el Resource. Mismo criterio que SPEC 10.
- **No:** totales por tarea en el backend. El frontend los calcula con el listado.
- **No:** tests automatizados. Consistente con SPEC 01–10; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Ninguna línea está como `product` en `lines.json` (todas son `pallet` salvo una `lot`). | Cambiar el `capture_type` de Recorte Frescos, Máquinas, Destajos y Urshell recorte antes de configurarles campos. |
| Faltan los `withSum` de `productRecords` en alguna consulta. | `recorded_pounds` cae al valor de las otras familias por el `?? 0`. Los criterios de aceptación revisan los 5 endpoints. |
| `raw_material` libre genera variantes del mismo nombre ("Brócoli" / "brocoli"). | Se acepta en esta spec. Ligarlo al catálogo va en otra spec. |
| La regla empacado ≤ recortadas no aplica en alguna línea donde se empaca sin recortar. | Si la línea no tiene `trimmed_lbs` asignado, el valor es null y la regla no se evalúa. |
| Se quita un campo custom de la línea con registros que tienen valor en `extra_values`. | El valor se conserva y se sigue devolviendo; el frontend no lo muestra. |

---

## Lo que **no** está en esta spec

- FK de `raw_material` al catálogo `raw_materials`.
- Unicidad de `raw_material` por tarea.
- Totales por tarea en el backend.
- Recalcular registros guardados.
- Cambiar el `capture_type` de las líneas existentes.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
