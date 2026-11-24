# SPEC 18 — Formato KPI de líneas `pallet` en el asistente IA

> **Estado:** Implementado
> **Depende de:** SPEC 08, SPEC 09, SPEC 12
> **Fecha:** 2026-11-24
> **Objetivo:** Hacer que `WeeklyPlanTaskAssistant` responda los KPI de las líneas `pallet` con la tabla de 15 columnas de `templates/kpi_format_pallet.xlsx`, calculada en PHP a partir de las tomas y del desecho capturado al finalizar la tarea.

---

## Por qué existe esta spec

Producción ya reporta las líneas por tarima con un Excel de 15 columnas (`templates/kpi_format_pallet.xlsx`). El asistente de SPEC 12 responde lo mismo con otro formato y otras definiciones. Dos diferencias importan:

- **Libras producidas.** El Excel usa `cajas × libras por caja`. `SummarizePalletRecords` llama `produced_lbs` a la suma del peso neto.
- **Desecho.** El Excel lo pide y el sistema no lo guarda en la familia `pallet`.

Los valores del Excel son literales, sin fórmulas. Las fórmulas de esta spec se dedujeron de sus 9 filas y cuadran en todas. También se comprobaron contra la tarea 3 de la base local (REEMPAQUE 5, SKU `PTC1240141`, 40 tarimas):

| Dato | Excel | Tarea 3 |
| ---- | ----- | ------- |
| Cajas producidas | 5200 | Σ `boxes` = 5200 |
| Libras producidas | 93600 | 5200 × 18 = 93600 |
| Libras de sobredosificación | 247 | Σ `difference` = 247 |
| Libras de desecho | 135 | no existe el dato |

`templates/` está en `.gitignore` y no llega a producción. El formato queda definido en esta spec y en el código; el agente no lee el archivo.

Flujo del usuario:

1. Al finalizar una tarea `pallet`, el usuario registra las libras de desecho (puede ser 0).
2. En el chat pregunta: "¿Cómo salió Reempaque 5 en la semana 46?".
3. El agente llama `GetPalletKpiReport`.
4. El agente responde con la tabla del formato, una fila por tarea, y la fila TOTAL si hay dos o más.

---

## Alcance

**Dentro:**

- Migración `add_waste_pounds_column_to_weekly_plan_tasks_table`: columna `waste_pounds`.
- `POST /weekly-plan-tasks/{id}/end`: `waste_pounds` obligatorio en tareas de líneas `pallet` (0 permitido) y opcional en `lot` y `product`.
- `PUT /weekly-plan-tasks/{id}`: `waste_pounds` opcional, para completar o corregir.
- `WeeklyPlanTaskResource`: key `waste_pounds`.
- `App\Calculators\PalletKpiCalculator`.
- `WeeklyPlanTaskAssistantService::getPalletKpiReport` y filtro `line_name`.
- `App\Http\Resources\WeeklyPlanTaskAssistant\PalletKpiReportResource`.
- Tool `App\Ai\Tools\GetPalletKpiReport` (la 15.ª del agente).
- `CaptureSummaryCalculator::pallet` y `SummarizePalletRecords`: dejan de presentar el peso neto como libras producidas.
- Instrucciones del agente en `resources/views/ai/weekly-plan-task-assistant.blade.php`.
- Documentación: `public/openapi.yaml`, `references/weekly-plan-task-execution.md` y `references/weekly-plan-task-assistant.md`.

**Fuera de alcance (para specs futuras):**

- Exportar el KPI a `.xlsx`. Queda para la SPEC 19 con esto ya decidido:
  - Se genera solo si el usuario pide Excel o descarga.
  - Las celdas llevan valores calculados en PHP, con formato de fecha, número y porcentaje. Sin fórmulas.
  - Se guarda en S3, con tabla propia y limpieza al borrar la conversación, como SPEC 13.
- Formato KPI de la familia `product` (`templates/kpi_format_product.xlsx`) y de `lot`.
- Endpoint REST del KPI. Solo existe como tool del agente.
- Captura de desecho por tarima o por tipo de desecho.
- Backfill de `waste_pounds` en tareas ya finalizadas.
- Cargar `boxes_per_pallet` o `presentation` faltantes en los SKUs.
- Igualar descripciones y clientes del sistema con los textos del Excel.
- Validar que el neto no sea menor que la boleta al capturar una tarima.
- Excluir del KPI las tarimas `RECHAZADO`.
- Bitácora (`LOGGED_FIELDS`) de cambios en `waste_pounds`.
- Ajustar `WeeklyPlanTaskAssistantDemoSeeder`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `add_waste_pounds_column_to_weekly_plan_tasks_table`

```php
Schema::table('weekly_plan_tasks', function (Blueprint $table) {
    $table->float('waste_pounds')->nullable()->after('weighed_pounds');
});
```

`down` elimina la columna. `null` significa "no registrado"; `0` significa "sin desecho". Las tareas existentes quedan en `null`.

`App\Models\WeeklyPlanTask` agrega `waste_pounds` a Fillable.

### `POST /weekly-plan-tasks/{id}/end`

`EndWeeklyPlanTaskRequest` resuelve la tarea desde `route('id')` con `performance.line`:

```text
produced_boxes  integer  required|min:0                       (sin cambios)
weighed_pounds  numeric  required|min:0                       (sin cambios)
waste_pounds    numeric  required|min:0   si la línea es pallet
                         nullable|min:0   en lot y product, o si la tarea no existe
```

Mensajes:

- `Las libras de desecho son obligatorias.`
- `Las libras de desecho deben ser un valor numérico.`
- `Las libras de desecho no pueden ser negativas.`

`WeeklyPlanTasksService::endWeeklyPlanTask` guarda `waste_pounds`. Si no viene en el body, conserva el valor que la tarea ya tenía. La tarea inexistente sigue dando 404 desde el Service.

```json
POST /weekly-plan-tasks/3/end
{ "produced_boxes": 5200, "weighed_pounds": 93847, "waste_pounds": 135 }
```

### `PUT /weekly-plan-tasks/{id}`

`UpdateWeeklyPlanTaskRequest` agrega `waste_pounds`: `nullable|numeric|min:0`, en cualquier familia y status. Mensajes de tipo y mínimo iguales a los de `end`.

### `WeeklyPlanTaskResource`

Agrega `'waste_pounds' => $this->waste_pounds`, después de `weighed_pounds`. `WeeklyPlanTaskAssistantTaskResource` la hereda.

### Columnas del formato

Una fila por tarea. `registros` son sus `weekly_plan_task_performance_records`, incluidos los `RECHAZADO`.

| # | Encabezado en la tabla | key | Origen o fórmula |
| - | ---------------------- | --- | ---------------- |
| 1 | FECHA | `operation_date` | `operation_date`, `d-m-Y`; `SIN PROGRAMACIÓN` si es `null` |
| 2 | LÍNEA | `line_name` | `performance.line.name` |
| 3 | SKU | `sku_code` | `performance.sku.code` |
| 4 | DESCRIPCIÓN | `sku_name` | `performance.sku.product_name` |
| 5 | CLIENTE | `client_name` | `performance.sku.client.name` |
| 6 | LIBRAS POR CAJA | `lbs_per_box` | `performance.sku.presentation` |
| 7 | CAJAS POR TARIMA | `boxes_per_pallet` | `performance.sku.boxes_per_pallet` |
| 8 | TARIMAS PRODUCIDAS | `produced_pallets` | `produced_boxes / boxes_per_pallet`; si no se puede, conteo de `pallet_number` distintos |
| 9 | CAJAS PRODUCIDAS | `produced_boxes` | Σ `boxes` de los registros |
| 10 | LIBRAS PRODUCIDAS | `produced_lbs` | `produced_boxes × lbs_per_box` |
| 11 | LIBRAS DE DESECHO | `waste_lbs` | `waste_pounds` de la tarea |
| 12 | % DE DESECHO | `waste_pct` | `waste_lbs / produced_lbs × 100` |
| 13 | LIBRAS DE SOBREDOSIFICACIÓN | `overdose_lbs` | Σ `difference` de los registros |
| 14 | % SOBREDOSIFICACIÓN | `overdose_pct` | `overdose_lbs / produced_lbs × 100` |
| 15 | LBS DE SOBREDOSIFICACIÓN POR TARIMA | `overdose_lbs_per_pallet` | `overdose_lbs / produced_pallets` |

Reglas:

- Suma de columna: ignora `null`. Si todas las filas son `null` → `null`.
- Un factor `null`, o un divisor `null` o 0, da `null`. Nunca hay división por cero.
- `produced_pallets` usa el conteo cuando `boxes_per_pallet` es `null` o 0, o cuando `produced_boxes` es `null`. Si tampoco hay `pallet_number`, queda `null`.
- El cálculo usa precisión completa. El redondeo ocurre solo en el Resource.

### `App\Calculators\PalletKpiCalculator`

```php
/**
 * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
 * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku.client and performance.line
 */
public function report(Collection $records, Collection $tasks): array
```

```php
[
    'tasks_count' => int,            // tareas con al menos un registro = filas
    'tasks_without_records' => int,  // tareas del alcance que no salen en la tabla
    'rows' => [
        [
            'task_id' => int,        // para preguntas de seguimiento; no se muestra
            'status' => int,         // idem
            /* las 15 keys de la tabla anterior */
            'pallets_source' => 'formula' | 'count' | null,
        ],
    ],
    'totals' => ?array,              // null con menos de 2 filas
]
```

- `rows` se ordena por `operation_date`, `line_name` y `task_id`. Las tareas sin fecha van al final.
- `totals` lleva:
  - Sumas: `produced_pallets`, `produced_boxes`, `produced_lbs`, `waste_lbs`, `overdose_lbs`.
  - `waste_pct` = Σ `waste_lbs` / Σ `produced_lbs` × 100, solo con las filas donde ambos no son `null`.
  - `overdose_pct` = Σ `overdose_lbs` / Σ `produced_lbs` × 100, con la misma regla.
  - `overdose_lbs_per_pallet` = Σ `overdose_lbs` / Σ `produced_pallets`, con la misma regla.

### `WeeklyPlanTaskAssistantService`

```php
public function getPalletKpiReport(array $scope): array;
```

- Mismo alcance que `summarizeCaptureRecords`: `task_id` **o** filtros, con los mismos errores. La resolución de tareas se extrae a un método privado que usan ambos.
- Acepta el filtro nuevo `line_name`: `whereLike` sobre `lines.name`, contiene, sin distinguir mayúsculas. Se combina con AND con `line_code`. Solo refina; no basta por sí solo.
- Todas las líneas `pallet` entran, no solo las de reempaque.
- Devuelve el array crudo del calculador.

### `PalletKpiReportResource`

Redondea a 2 decimales todos los números, excepto `lbs_per_box`, que sale completo (hay presentaciones como `22.046`). Los porcentajes van en escala 0–100, como en SPEC 12. `null` se mantiene como `null`.

### Tool `App\Ai\Tools\GetPalletKpiReport`

- Usa `DefinesTaskArguments` y `RespondsToAssistant`.
- Argumentos: `task_id?`, los filtros de tareas de SPEC 12 y `line_name?` (solo en esta tool).
- Mensaje: `El nombre de línea debe ser texto`.
- Devuelve `PalletKpiReportResource`.

### Cambio en `SummarizePalletRecords`

En la familia `pallet`, `CaptureSummaryCalculator` cambia la key `produced_lbs` por `net_weight`, en la raíz y en `by_task`. El valor no cambia (Σ `net_weight`). `lot` y `product` conservan `produced_lbs`. `progress_pct` no cambia.

La descripción de la tool dice que sirve para avance, báscula, tara, litros, neto y status. Para producción, desecho o sobredosificación remite a `GetPalletKpiReport`.

### Instrucciones del agente

`resources/views/ai/weekly-plan-task-assistant.blade.php` agrega la sección **Formato KPI de líneas `pallet`**:

- Preguntas de KPI, resumen, rendimiento o producción de líneas `pallet` → llamar `GetPalletKpiReport` y responder con la tabla markdown de 15 columnas, con los encabezados y el orden de esta spec.
- Pregunta puntual (por ejemplo "¿qué % de sobredosificación tuvo Reempaque 5?") → misma tool, pero responder solo ese dato.
- Empleados, bitácora, tiempos muertos, observaciones y el detalle tarima por tarima siguen con sus tools actuales.
- Con dos o más filas, agregar al final la fila **TOTAL** con `totals`. LIBRAS POR CAJA, CAJAS POR TARIMA y las columnas de texto van vacías.
- `null` se escribe `sin dato`, nunca `0`.
- En la tabla, los números van sin unidad y los porcentajes con `%`.
- La tabla no muestra el id de la tarea: es la excepción a la regla de citarlo. `task_id` solo se usa para responder preguntas de seguimiento.
- Al pie de la tabla, solo si aplica:
  - cuántas tareas quedaron fuera por no tener tomas (`tasks_without_records`);
  - qué filas usan conteo de tarimas porque el SKU no tiene cajas por tarima (`pallets_source = count`).
- Sin filas, decir que no hay tomas en ese alcance; no mostrar una tabla vacía.
- Si el usuario nombra la línea ("Reempaque 5"), usar `line_name`. AM y PM son líneas distintas y salen en filas separadas.

Otros cambios en la vista:

- Familia `pallet`: "libras producidas" = cajas × libras por caja; sobredosificación = suma de diferenciales. El peso neto no son libras producidas.
- Sección **Totales y porcentajes**: en `pallet`, producción, desecho y sobredosificación salen de `GetPalletKpiReport`.
- La regla de citar el id de la tarea menciona la excepción.

### Ejemplo

Pregunta: "¿Cómo salió Reempaque 5 el 10 de noviembre de 2026?", con la tarea 3 y `waste_pounds = 135`.

| FECHA | LÍNEA | SKU | DESCRIPCIÓN | CLIENTE | LIBRAS POR CAJA | CAJAS POR TARIMA | TARIMAS PRODUCIDAS | CAJAS PRODUCIDAS | LIBRAS PRODUCIDAS | LIBRAS DE DESECHO | % DE DESECHO | LIBRAS DE SOBREDOSIFICACIÓN | % SOBREDOSIFICACIÓN | LBS DE SOBREDOSIFICACIÓN POR TARIMA |
| ----- | ----- | --- | ----------- | ------- | --------------- | ---------------- | ------------------ | ---------------- | ----------------- | ----------------- | ------------ | --------------------------- | ------------------- | ----------------------------------- |
| 10-11-2026 | REEMPAQUE 5 | PTC1240141 | STRAWBERRY BANANA 6X48 OZ | WALMART USA | 18 | 130 | 40 | 5200 | 93600 | 135 | 0.14 % | 247 | 0.26 % | 6.18 |

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Columna `waste_pounds`.** Migración, Fillable del modelo y key en `WeeklyPlanTaskResource`. Verificación: `php artisan migrate` sin error; `GET /weekly-plan-tasks/3` devuelve `waste_pounds: null`.
2. **Corrección por `PUT`.** Regla y mensajes en `UpdateWeeklyPlanTaskRequest`. Verificación: `PUT /weekly-plan-tasks/3` con `waste_pounds = 135` lo guarda.
3. **Captura al finalizar.** Regla por familia en `EndWeeklyPlanTaskRequest` y guardado en `endWeeklyPlanTask`.
4. **`PalletKpiCalculator`.** Crear `app/Calculators/PalletKpiCalculator.php` con filas y totales. Verificación en tinker con modelos sin guardar, armados con las 9 filas del Excel.
5. **Service y Resource.** Extraer la resolución de tareas, agregar `line_name` y `getPalletKpiReport` (Interface y Service), y crear `PalletKpiReportResource`. Verificación: `summarizeCaptureRecords` devuelve lo mismo que antes.
6. **Tool.** `php artisan make:tool GetPalletKpiReport`. Registrarla en `WeeklyPlanTaskAssistant::TOOLS`. Verificación en tinker llamando `handle()`.
7. **`SummarizePalletRecords`.** Key `net_weight` en la familia `pallet` de `CaptureSummaryCalculator` y descripción nueva de la tool.
8. **Instrucciones.** Sección KPI y ajustes en la vista Blade.
9. **OpenAPI.** `waste_pounds` en `POST …/end`, `PUT /weekly-plan-tasks/{id}` y el schema `WeeklyPlanTask` de `public/openapi.yaml`.
10. **Guías.**
    - `references/weekly-plan-task-execution.md`: campo, regla por familia, mensajes 422 y tipo `EndTaskBody`.
    - `references/weekly-plan-task-assistant.md`: formato KPI, columnas, fórmulas y preguntas de ejemplo.
11. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

**Desecho**

- [X] `php artisan migrate` agrega `weekly_plan_tasks.waste_pounds` nullable; las tareas existentes quedan en `null`.
- [X] `POST …/end` de una tarea `pallet` sin `waste_pounds` → 422 `Las libras de desecho son obligatorias.` y la tarea sigue en status 4.
- [X] `POST …/end` de una tarea `pallet` con `waste_pounds = 0` → 200 y guarda `0`.
- [X] `POST …/end` de una tarea `pallet` con `waste_pounds = -1` → 422 `Las libras de desecho no pueden ser negativas.`
- [X] `POST …/end` de una tarea `lot` o `product` sin `waste_pounds` → 200 y queda `null`; con `waste_pounds = 12.5` → 200 y guarda `12.5`.
- [X] `POST …/end` de una tarea inexistente → 404 `La tarea del plan semanal no existe`, con `waste_pounds` o sin él.
- [X] `PUT /weekly-plan-tasks/3` con `waste_pounds = 135` lo guarda; sin la key, conserva el valor.
- [X] `GET /weekly-plan-tasks/{id}` devuelve `waste_pounds`.

**Cálculo**

- [ ] La tarea 3 con `waste_pounds = 135` da una fila con `produced_pallets = 40`, `produced_boxes = 5200`, `produced_lbs = 93600`, `waste_lbs = 135`, `waste_pct = 0.14`, `overdose_lbs = 247`, `overdose_pct = 0.26` y `overdose_lbs_per_pallet = 6.18`, y `totals = null`.
- [ ] La tarea 3 con `waste_pounds = null` da `waste_lbs = null` y `waste_pct = null`; las demás columnas no cambian.
- [ ] Las 9 filas del Excel dan `totals` con `produced_pallets = 176.34`, `produced_boxes = 15472`, `produced_lbs = 300109.1`, `waste_lbs = 711`, `waste_pct = 0.24`, `overdose_lbs = 1215`, `overdose_pct = 0.4` y `overdose_lbs_per_pallet = 6.89`.
- [ ] Con 24 cajas, 70 cajas por tarima, 22.046 lbs por caja y 9 lbs de diferencial: `produced_pallets = 0.34`, `produced_lbs = 529.1`, `overdose_lbs_per_pallet = 26.25` y `lbs_per_box = 22.046`.
- [ ] Un SKU sin `boxes_per_pallet` y 3 números de tarima distintos da `produced_pallets = 3` y `pallets_source = count`.
- [ ] Un SKU sin `presentation` da `produced_lbs`, `waste_pct` y `overdose_pct` en `null`, sin error.
- [ ] Una línea sin `difference` asignado da `overdose_lbs`, `overdose_pct` y `overdose_lbs_per_pallet` en `null`.
- [ ] Un registro `RECHAZADO` cuenta en cajas, libras y sobredosificación.
- [ ] Una tarea sin tomas no aparece en `rows` y suma 1 a `tasks_without_records`.
- [ ] `totals` excluye del `waste_pct` las libras producidas de las filas con `waste_lbs = null`.

**Tool y agente**

- [ ] `GetPalletKpiReport` sobre una tarea `lot` → `Error: La tarea {id} captura por lote, no por tarima`.
- [ ] `GetPalletKpiReport` con `task_id` y filtros a la vez → `Error: Indica una tarea o filtros, no ambos`.
- [ ] `GetPalletKpiReport` solo con `line_name` → `Error: Acota la búsqueda con un plan semanal, una semana o un rango de fechas`.
- [ ] `line_name = "reempaque 5"` con una fecha devuelve tareas de `REEMPAQUE 5` y `REEMPAQUE 5 PM`.
- [ ] El reporte de una semana no hace consultas N+1.
- [ ] `SummarizePalletRecords` no devuelve la key `produced_lbs`; devuelve `net_weight` con el mismo valor. `SummarizeLotRecords` y `SummarizeProductRecords` no cambian.
- [ ] "Dame el resumen de Reempaque 5 del 10 de noviembre de 2026" responde con la tabla de 15 columnas, en el orden de esta spec y sin id de tarea.
- [ ] "¿Qué % de sobredosificación tuvo Reempaque 5 el 10 de noviembre de 2026?" responde 0.26 % sin mostrar la tabla.
- [ ] Un resumen con dos o más filas muestra la fila TOTAL con los valores de `totals`.
- [ ] El desecho no registrado aparece como `sin dato`, no como `0`.
- [ ] "¿Qué empleados tiene la tarea 3?" no llama `GetPalletKpiReport`.
- [ ] `GetPalletKpiReport` no ejecuta `insert`, `update` ni `delete` (revisión de código).
- [ ] `public/openapi.yaml`, `references/weekly-plan-task-execution.md` y `references/weekly-plan-task-assistant.md` documentan los cambios.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** dividir en dos specs. Desecho, KPI en el chat y exportación `.xlsx` son tres áreas; el archivo va en la SPEC 19.
- **Sí:** desecho al finalizar la tarea. Un valor por tarea, misma granularidad que el Excel.
- **No:** desecho por tarima. El Excel no lo tiene y pediría el dato en cada toma.
- **Sí:** `waste_pounds` obligatorio en `pallet`, con 0 válido. Decisión del usuario. Distingue "sin desecho" de "no registrado".
- **Sí:** opcional y guardado en `lot` y `product`. Decisión del usuario, aunque solo el KPI de `pallet` lo usa.
- **Sí:** corrección por el `PUT` existente. Igual que `weighed_pounds`; evita otro endpoint.
- **No:** backfill. No hay fuente del dato en el sistema.
- **Sí:** nombre `waste_pounds`. Sigue a `weighed_pounds`, su columna hermana.
- **Sí:** cajas y sobredosificación desde las tomas. Sirve para tareas en progreso y finalizadas.
- **No:** usar `produced_boxes` y `weighed_pounds` del cierre. Son digitados y no existen hasta finalizar.
- **Sí:** libras producidas = cajas × presentación, no Σ `ticket_weight`. Es la fórmula del Excel y no depende de que la línea tenga asignado `ticket_weight`.
- **Sí:** tarimas por fórmula y conteo como respaldo. 277 de 400 SKUs no tienen `boxes_per_pallet`; sin respaldo, casi todo REEMPAQUE 9 quedaría vacío.
- **Sí:** `pallets_source` en la salida. Deja ver qué filas no usan la fórmula del Excel.
- **Sí:** sobredosificación = suma directa de diferenciales. El usuario confirma que el neto nunca pesa menos que la boleta; no hay tratamiento de negativos.
- **Sí:** las tarimas `RECHAZADO` cuentan. Decisión del usuario; igual que `SummarizePalletRecords`.
- **Sí:** una fila por tarea. Trazable con los datos del sistema.
- **No:** agrupar por fecha + línea + SKU. Mezclaría tareas divididas.
- **Sí:** tabla sin id de tarea. Decisión del usuario; conserva las 15 columnas exactas. `task_id` viaja en la salida de la tool para el seguimiento.
- **Sí:** solo tareas con tomas. Las pendientes llenarían la tabla de `sin dato`; se informa cuántas quedaron fuera.
- **Sí:** FECHA = `operation_date`. Es la fecha con la que filtran las tools.
- **Sí:** encabezado LÍNEA en vez de REEMPAQUE. La familia `pallet` incluye IQF, jugos y chocolate.
- **Sí:** tabla en preguntas de resumen o rendimiento y dato solo en preguntas puntuales. Decisión del usuario.
- **Sí:** tool nueva `GetPalletKpiReport`. Deja `SummarizePalletRecords` para avance, báscula, tara y litros.
- **Sí:** renombrar `produced_lbs` a `net_weight` en el resumen `pallet`. Evita dos números distintos con el mismo nombre (93847 y 93600 en la tarea 3).
- **Sí:** `PalletKpiCalculator` propio. Sus fórmulas son por tarea y con datos del SKU; `CaptureSummaryCalculator` suma columnas de registros.
- **Sí:** fila TOTAL con dos o más filas, calculada en PHP y ponderada. El LLM no hace aritmética (SPEC 12).
- **Sí:** filtro `line_name` solo en esta tool. El usuario habla de "Reempaque 5", no de `2REM5A`.
- **Sí:** porcentajes en escala 0–100 con 2 decimales. Convención de SPEC 12; el Excel los guarda como fracción.
- **Sí:** `lbs_per_box` sin redondear. Con 2 decimales, 22.046 saldría 22.05.
- **Sí:** descripción y cliente del sistema. No siempre coinciden con el Excel (`WALMART USA` vs `WALMART`).
- **No:** leer el `.xlsx` en tiempo de ejecución. `templates/` no se versiona y el archivo no trae fórmulas.
- **No:** tests automatizados. Consistente con specs anteriores.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| `POST …/end` sin `waste_pounds` empieza a dar 422 en líneas `pallet` y el frontend actual no puede finalizar tareas. | Desplegar junto con el cambio del frontend. `references/weekly-plan-task-execution.md` documenta el campo. |
| Varias líneas son `pallet` sin campos configurados (`DESTAJO3`, `1LNDTB`…) y también exigen desecho al finalizar. | La regla usa solo `capture_type`. Se corrige la familia de la línea o se envía `0`. |
| Un diferencial negativo (tara o báscula mal digitada) resta sobredosificación sin aviso. | Aceptado por el usuario como caso imposible. `ListPalletRecords` permite ver la tarima. |
| `WeeklyPlanTaskAssistantDemoSeeder` genera diferenciales mayormente negativos y sin desecho: el KPI sobre esos datos se ve irreal. | No usarlo para validar el KPI. Ajustarlo queda fuera de alcance. |
| El LLM usa `SummarizePalletRecords` para una pregunta de KPI. | Descripciones de ambas tools, instrucciones explícitas y la key `produced_lbs` eliminada del resumen `pallet`. |
| El LLM reordena, omite o suma columnas. | Encabezados y orden fijos en la vista; totales calculados en PHP; criterios de aceptación sobre la tabla. |
| Un mes de todas las líneas `pallet` produce una tabla muy larga de 15 columnas. | Rango máximo de SPEC 12. La descarga `.xlsx` de la SPEC 19 cubre ese uso. |
| `line_name` "contiene" trae más líneas de las esperadas ("Reempaque 1" también trae "Reempaque 1 PM"). | Es lo pedido para AM y PM; la columna LÍNEA las distingue y `line_code` acota a una. |
| El cambio de `presentation` o `boxes_per_pallet` de un SKU altera KPI pasados. | Aceptado: usa los valores actuales del SKU, igual que `planned_pounds`. |

---

## Lo que **no** está en esta spec

- Exportación a `.xlsx` (SPEC 19).
- Formato KPI de `product` y `lot`.
- Endpoint REST del KPI.
- Desecho por tarima o por tipo.
- Backfill de desecho.
- Carga de `boxes_per_pallet` y `presentation` faltantes.
- Validación de neto contra boleta al capturar.
- Exclusión de tarimas `RECHAZADO`.
- Bitácora de `waste_pounds`.
- Cambios en `WeeklyPlanTaskAssistantDemoSeeder`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
