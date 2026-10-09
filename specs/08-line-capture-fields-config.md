# SPEC 08 — Configuración de campos de captura por línea

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-10-08
> **Objetivo:** Definir por línea qué campos de producción se capturan, con un catálogo de campos (`capture_fields`), una familia de captura por línea (`lines.capture_type`) y la asignación entre ambos (`line_fields`) que el frontend usa para construir el formulario.

---

## Por qué existe esta spec

Cada línea llena hoy en Excel una tabla "1. PRODUCCIÓN LÍNEA" con columnas distintas. Analizando los archivos de `templates_excels/` salen 3 familias de captura:

| Familia (`capture_type`) | Líneas | Una fila es… |
| ------------------------ | ------ | ------------ |
| `pallet` | Empaque Frescos, Reempaques, IQF Túnel, Urshell IQF, Chocolatera, Empaque Jugos | una tarima |
| `lot` | Recorte LD, Recorte Jugos, Ejotera | un lote (GRN) de materia prima |
| `product` | Recorte Frescos, Máquinas, Destajos, Urshell recorte | una variante de producto o materia prima |

Dentro de una misma familia las fórmulas son las mismas. Lo que cambia por línea es **qué campos se capturan** (ej. `ESTADO` y `LITROS` solo en Jugos).

Esta spec solo resuelve la **configuración**: qué familia tiene cada línea y qué campos usa. La captura de registros, las calculadoras y el guardado de valores van en specs separadas por familia (09 `pallet`, 10 `lot`, 11 `product`).

Flujo del usuario:

1. La línea tiene una familia (`capture_type`), por default `pallet`.
2. El usuario asigna a la línea campos del catálogo: los de sistema de su familia, los globales y los custom. Para cada uno define si es obligatorio, en qué orden aparece y opcionalmente una etiqueta propia.
3. El frontend pide `GET /lines/{code}/fields` y arma el formulario con esa lista.
4. Si se necesita un dato nuevo que no entra en cálculos, el usuario crea un campo custom en el catálogo y lo asigna a las líneas que lo usan.

---

## Alcance

**Dentro:**

- Columna `capture_type` en `lines` (default `pallet`) y enum `App\Enums\CaptureType`.
- Enum `App\Enums\CaptureFieldDataType`.
- Tabla `capture_fields`, modelo `CaptureField` y feature `CaptureFields` (Interface, Service, Provider, Requests, Resources, Controller, `routes/capturefields.php`).
- `CaptureFieldsSeeder` con los campos de sistema de las 3 familias más el global `observations`.
- CRUD de campos custom: `GET`, `POST`, `PATCH`, `DELETE /capture-fields`. Los de sistema son de solo lectura.
- Tabla `line_fields`, modelo `LineField` y feature `LineFields` (Interface, Service, Provider, Requests, Resource, Controller), con rutas en `routes/lines.php`.
- `GET /lines/{code}/fields` (formulario de la línea), `POST /lines/{code}/fields`, `PATCH /line-fields/{id}`, `DELETE /line-fields/{id}`.
- Validación de dependencias de campos calculados al asignar y al quitar.
- `capture_type` opcional en `CreateLineRequest` / `UpdateLineRequest` y en `LineResource`.
- Bloqueo del cambio de `capture_type` si la línea tiene campos configurados.
- Documentación en `public/openapi.yaml` y guía de integración en `references/line-capture-fields.md`.

**Fuera de alcance (para specs futuras):**

- Capturar registros y guardar valores (tablas por familia, columna `extra_values`). Va en SPEC 09 (`pallet`), 10 (`lot`) y 11 (`product`).
- Calculadoras (`PalletCalculator`, etc.) y las fórmulas concretas de cada campo calculado, incluida la convención de signo del diferencial.
- Adaptar `weekly_plan_task_performance_records` a los campos configurables (SPEC 09).
- Constantes por SKU o LineSku (lbs por caja, tara por caja, tara de tarima).
- Módulos extra por línea: Ciclos HPP, Llenado tanque/prensa, tiempos muertos por área.
- Configurar campos por línea + SKU.
- Copiar la configuración de una línea a otra.
- `capture_type` en la carga masiva de líneas (`POST /lines/uploadFile`). Las líneas cargadas por Excel quedan en `pallet`.
- Restringir la configuración a admin. Todo queda bajo `jwt.auth`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Enums (`app/Enums/`, carpeta nueva)

```php
enum CaptureType: string
{
    case Pallet = 'pallet';
    case Lot = 'lot';
    case Product = 'product';
}

enum CaptureFieldDataType: string
{
    case Number = 'number';
    case Integer = 'integer';
    case Text = 'text';
    case Date = 'date';
    case Time = 'time';
    case Boolean = 'boolean';
    case Select = 'select';
}
```

### Migración: `add_capture_type_column_to_lines_table`

```php
Schema::table('lines', function (Blueprint $table) {
    $table->string('capture_type', 20)->default('pallet')->after('shift');
});
```

Las líneas existentes quedan en `pallet`. Las de recorte se corrigen a mano con `PUT /lines/{code}` antes de configurarles campos.

### Migración: `create_capture_fields_table`

```php
Schema::create('capture_fields', function (Blueprint $table) {
    $table->id();
    $table->string('key', 50);
    $table->string('label', 100);
    $table->string('data_type', 20);
    $table->string('capture_type', 20)->nullable();
    $table->boolean('is_system')->default(false);
    $table->boolean('is_calculated')->default(false);
    $table->json('depends_on')->nullable();
    $table->json('options')->nullable();
    $table->timestamps();

    $table->unique(['capture_type', 'key']);
});
```

| Columna         | Significado |
| --------------- | ----------- |
| `key`           | Identificador en snake_case (`^[a-z][a-z0-9_]*$`). Es el nombre del campo en los registros de las specs de familia. |
| `label`         | Etiqueta por default en español. |
| `data_type`     | `CaptureFieldDataType`. |
| `capture_type`  | Familia del campo. `null` = global (se puede asignar a líneas de cualquier familia). Todos los custom son globales. |
| `is_system`     | Lo crea el seeder; respaldado por columna y usado por calculadoras en las specs de familia. Solo lectura por API. |
| `is_calculated` | Es una salida, no se captura. El frontend lo muestra como solo lectura. |
| `depends_on`    | Arreglo de `key` de la misma familia que el campo calculado necesita. `null` si no es calculado. |
| `options`       | Arreglo de strings. Solo para `data_type = select`; `null` en los demás. |

El índice único `(capture_type, key)` no frena duplicados con `capture_type = null` en `pgsql`. Por eso el Service valida que la `key` de un campo custom no exista en **ninguna** fila del catálogo.

### Migración: `create_line_fields_table`

```php
Schema::create('line_fields', function (Blueprint $table) {
    $table->id();
    $table->foreignId('line_id')->constrained()->cascadeOnDelete();
    $table->foreignId('capture_field_id')->constrained();
    $table->boolean('is_required')->default(false);
    $table->unsignedInteger('order')->default(0);
    $table->string('label', 100)->nullable();
    $table->timestamps();

    $table->unique(['line_id', 'capture_field_id']);
});
```

| Columna            | Significado |
| ------------------ | ----------- |
| `capture_field_id` | Campo del catálogo. Sin cascade: un campo asignado no se borra (el Service responde 400 antes de llegar a la FK). |
| `is_required`      | Si la captura lo exige. Siempre `false` en calculados. |
| `order`            | Posición en el formulario. No es único; empates se ordenan por `id`. |
| `label`            | Etiqueta propia de la línea. `null` = usa la del catálogo. |

### Modelos

`App\Models\CaptureField`:

- Fillable: `key`, `label`, `data_type`, `capture_type`, `is_system`, `is_calculated`, `depends_on`, `options`.
- Casts: `data_type` → `CaptureFieldDataType`, `capture_type` → `CaptureType`, `is_system` / `is_calculated` → `boolean`, `depends_on` / `options` → `array`.
- Relaciones: `lineFields()` → `hasMany(LineField::class)`.

`App\Models\LineField`:

- Fillable: `line_id`, `capture_field_id`, `is_required`, `order`, `label`.
- Casts: `is_required` → `boolean`.
- Relaciones: `line()` → `Line`, `captureField()` → `CaptureField`.

Cambios en `App\Models\Line`:

- Fillable: agrega `capture_type`.
- Cast: `capture_type` → `CaptureType`.
- Relación: `lineFields()` → `hasMany(LineField::class)`.

### Seeder: `CaptureFieldsSeeder`

Idempotente: `updateOrCreate` por `(capture_type, key)`. Se registra en `DatabaseSeeder`. Todos con `is_system = true`.

**Global** (`capture_type = null`):

| key | label | data_type |
| --- | ----- | --------- |
| `observations` | Observaciones | text |

**`pallet`:**

| key | label | data_type | calculado | depends_on |
| --- | ----- | --------- | --------- | ---------- |
| `pallet_number` | Tarima # | integer | | |
| `lot` | Lote | text | | |
| `recorded_at` | Hora | time | | |
| `boxes` | Cajas | integer | | |
| `liters` | Litros | number | | |
| `status` | Estado | select (`APROBADO`, `RECHAZADO`) | | |
| `ticket_weight` | Peso boleta | number | | |
| `scale_weight` | Peso báscula | number | | |
| `tare` | Tara | number | | |
| `net_weight` | Peso neto | number | sí | `scale_weight`, `tare` |
| `difference` | Diferencial | number | sí | `net_weight`, `ticket_weight` |

**`lot`:**

| key | label | data_type | calculado | depends_on |
| --- | ----- | --------- | --------- | ---------- |
| `entry_date` | Fecha de ingreso | date | | |
| `lot` | Lote (GRN) | text | | |
| `recorded_at` | Hora | time | | |
| `intake_lbs` | Peso de libras al ingreso | number | | |
| `applied_raw_lbs` | MP aplicada | number | | |
| `trimmed_lbs` | Libras recortadas | number | | |
| `overripe_lbs` | Libras sobremaduro | number | | |
| `recovery_pct` | % Recuperación | number | sí | `trimmed_lbs`, `applied_raw_lbs` |
| `overripe_pct` | % Sobremadurez | number | sí | `overripe_lbs`, `applied_raw_lbs` |
| `grn_balance` | Saldo GRN | number | sí | `intake_lbs`, `applied_raw_lbs` |

**`product`:**

| key | label | data_type | calculado | depends_on |
| --- | ----- | --------- | --------- | ---------- |
| `raw_material` | Materia prima | text | | |
| `raw_lbs` | Libras materia prima | number | | |
| `classified_lbs` | Libras clasificadas | number | | |
| `trimmed_lbs` | Libras recortadas | number | | |
| `rejected_lbs` | Rechazo | number | | |
| `packed_lbs` | Empacado | number | | |
| `recovery_pct` | % Recuperación | number | sí | `trimmed_lbs`, `raw_lbs` |
| `rejection_pct` | % Rechazo | number | sí | `rejected_lbs`, `raw_lbs` |

Las fórmulas exactas de los calculados se definen en la spec de cada familia. Aquí solo se fija de qué campos dependen.

### Endpoints — `CaptureFields`

En `routes/capturefields.php` bajo `jwt.auth`, incluido con `require` en `routes/api.php`. `apiResource('/capture-fields')` con `parameters(['capture-fields' => 'id'])`.

| Método   | Ruta                   | Body / query | Respuesta |
| -------- | ---------------------- | ------------ | --------- |
| `GET`    | `/capture-fields`      | `?captureType=`, `?isSystem=`, `?limit=` | 200, `CaptureFieldResource::collection` o `PaginatedCaptureFieldsResource` |
| `GET`    | `/capture-fields/{id}` | — | 200, `CaptureFieldResource` |
| `POST`   | `/capture-fields`      | `key`, `label`, `data_type`, `options?` | 201, `CaptureFieldResource` del creado |
| `PATCH`  | `/capture-fields/{id}` | `key?`, `label?`, `data_type?`, `options?` | 200, `data: null` |
| `DELETE` | `/capture-fields/{id}` | — | 200, `data: null` |

Filtros del listado:

- `captureType=pallet` → campos de esa familia **más** los globales (`capture_type = null`). Es la lista que el frontend ofrece al configurar una línea de esa familia.
- `isSystem=true|false` → filtra por `is_system`.
- Orden: `capture_type` (globales al final), luego `id`.

Mensajes de éxito:

- Listado / detalle: `Campos Obtenidos Correctamente` / `Campo Obtenido Correctamente`.
- Crear: `Campo Creado Correctamente`.
- Editar: `Campo Actualizado Correctamente`.
- Eliminar: `Campo Eliminado Correctamente`.

### Requests — `CaptureFields`

`CreateCaptureFieldRequest`:

```text
key         string  required|max:50|regex:/^[a-z][a-z0-9_]*$/
label       string  required|max:100
data_type   string  required|Rule::enum(CaptureFieldDataType)
options     array   required_if:data_type,select|prohibited_unless:data_type,select|min:1
options.*   string  required|distinct|max:100
```

`UpdateCaptureFieldRequest`: las mismas reglas con `sometimes`. Si `data_type` llega como `select`, `options` es obligatorio.

`capture_type`, `is_system`, `is_calculated` y `depends_on` no se aceptan en el body: un campo custom siempre es global, de entrada y sin dependencias.

### Reglas de negocio — `CaptureFields`

- Campo inexistente → `NotFoundError('El campo no existe')`.
- **Crear:** si la `key` existe en cualquier fila del catálogo → `BadRequestError('Ya existe un campo con la clave {key}')`. Se guarda con `capture_type = null`, `is_system = false`, `is_calculated = false`, `depends_on = null`.
- **Editar:**
  - Campo de sistema → `BadRequestError('Los campos de sistema no se pueden modificar')`.
  - Cambio de `key` o `data_type` en un campo asignado a alguna línea → `BadRequestError('No se puede cambiar la clave o el tipo de un campo asignado a líneas')`.
  - Nueva `key` repetida → mismo 400 que al crear.
  - Si `data_type` deja de ser `select`, `options` pasa a `null`.
  - `label` y `options` se pueden editar siempre.
- **Eliminar:**
  - Campo de sistema → `BadRequestError('Los campos de sistema no se pueden eliminar')`.
  - Campo asignado a alguna línea → `BadRequestError('No se puede eliminar un campo asignado a líneas')`.

### `CaptureFieldResource`

```php
[
    'id' => $this->id,
    'key' => $this->key,
    'label' => $this->label,
    'data_type' => $this->data_type,
    'capture_type' => $this->capture_type,
    'is_system' => $this->is_system,
    'is_calculated' => $this->is_calculated,
    'depends_on' => $this->depends_on ?? [],
    'options' => $this->options,
    'is_assigned' => (bool) $this->line_fields_exists,
]
```

El Service carga `withExists('lineFields')` en listado y detalle. `is_assigned` le dice al frontend si `key` y `data_type` todavía se pueden editar. `PaginatedCaptureFieldsResource` con `data`, `total`, `currentPage`, `lastPage`.

### Endpoints — `LineFields`

En `routes/lines.php`, dentro del grupo `jwt.auth`, en un bloque `// FUNCTIONALITYS`. Los atiende `LineFieldsController`. La línea se identifica por `code`, igual que `/lines/{line}`.

| Método   | Ruta                   | Body | Respuesta |
| -------- | ---------------------- | ---- | --------- |
| `GET`    | `/lines/{code}/fields` | — | 200, `LineFieldResource::collection` |
| `POST`   | `/lines/{code}/fields` | `capture_field_id`, `is_required?`, `order?`, `label?` | 201, `LineFieldResource` del creado |
| `PATCH`  | `/line-fields/{id}`    | `is_required?`, `order?`, `label?` | 200, `data: null` |
| `DELETE` | `/line-fields/{id}`    | — | 200, `data: null` |

`GET /lines/{code}/fields` es el endpoint del formulario: devuelve los campos ordenados por `order` y luego `id`, con `with('captureField')`.

Mensajes de éxito:

- Listado: `Campos de la Línea Obtenidos Correctamente`.
- Asignar: `Campo Asignado Correctamente`.
- Editar: `Campo de la Línea Actualizado Correctamente`.
- Quitar: `Campo Quitado de la Línea Correctamente`.

### Requests — `LineFields`

`CreateLineFieldRequest`:

```text
capture_field_id  integer  required|exists:capture_fields,id
is_required       boolean  sometimes|boolean
order             integer  sometimes|integer|min:0
label             string   sometimes|nullable|max:100
```

`UpdateLineFieldRequest`:

```text
is_required  boolean  sometimes|boolean
order        integer  sometimes|integer|min:0
label        string   sometimes|nullable|max:100
```

`capture_field_id` no se edita: para cambiar de campo se quita y se asigna otro.

### Reglas de negocio — `LineFields`

El Service depende de `LinesServiceInterface` (para `getLineByCode` y su `NotFoundError`) y de `CaptureFieldsServiceInterface` (para `getCaptureFieldById`).

- Línea inexistente → `NotFoundError('La línea no existe')` (reutiliza `getLineByCode`).
- Asignación inexistente → `NotFoundError('El campo de la línea no existe')`.
- **Asignar:**
  - El campo no es global y su `capture_type` es distinto al de la línea → `BadRequestError('El campo no pertenece a la familia de captura de la línea')`.
  - El campo ya está asignado a la línea → `BadRequestError('El campo ya está asignado a la línea')`.
  - Campo calculado con `is_required = true` → `BadRequestError('Un campo calculado no puede ser obligatorio')`.
  - Campo calculado con alguna `key` de `depends_on` no asignada a la línea (buscando en campos de la misma familia) → `BadRequestError('Para asignar {label} primero asigna: {labels faltantes}')`.
- **Editar:** `is_required = true` en un calculado → mismo 400 que al asignar. `label: null` explícito vuelve a la etiqueta del catálogo.
- **Quitar:** si algún calculado asignado a la misma línea tiene la `key` del campo en su `depends_on` → `BadRequestError('No se puede quitar {label}: lo usa {labels de calculados}')`.

### `LineFieldResource`

```php
[
    'id' => $this->id,
    'line_id' => $this->line_id,
    'capture_field_id' => $this->capture_field_id,
    'key' => $this->captureField->key,
    'label' => $this->label ?? $this->captureField->label,
    'field_label' => $this->captureField->label,
    'custom_label' => $this->label,
    'data_type' => $this->captureField->data_type,
    'is_system' => $this->captureField->is_system,
    'is_calculated' => $this->captureField->is_calculated,
    'depends_on' => $this->captureField->depends_on ?? [],
    'options' => $this->captureField->options,
    'is_required' => $this->is_required,
    'order' => $this->order,
]
```

### Cambios en `Lines`

- `CreateLineRequest` y `UpdateLineRequest`: `capture_type` → `sometimes|Rule::enum(CaptureType::class)`, con mensaje en español (`La familia de captura no es válida.`). Si no viene al crear, queda el default de BD.
- `LinesService::updateLineById`: si `capture_type` viene, es distinto al actual y la línea tiene `lineFields` → `BadRequestError('No se puede cambiar la familia de captura de una línea con campos configurados')`.
- `LineResource`: agrega `'capture_type' => $this->capture_type`.
- `POST /lines/uploadFile` no cambia.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Enums y `capture_type` en líneas.** Crear `app/Enums/CaptureType.php` y `app/Enums/CaptureFieldDataType.php`. Migración `add_capture_type_column_to_lines_table`. Fillable, cast y `capture_type` en `LineResource`, `CreateLineRequest` y `UpdateLineRequest`. Verificación: `php artisan migrate`; `GET /lines` devuelve `capture_type: "pallet"` en las existentes.
2. **Catálogo: migración, modelo y seeder.** `php artisan make:model CaptureField -m --no-interaction`. Columnas, índice, fillable, casts y relación. `CaptureFieldsSeeder` registrado en `DatabaseSeeder`. Verificación: `php artisan db:seed --class=CaptureFieldsSeeder` dos veces seguidas deja 30 filas (1 global + 11 `pallet` + 10 `lot` + 8 `product`).
3. **Feature `CaptureFields`.** Interface, Service, Provider registrado en `bootstrap/providers.php`, Requests, `CaptureFieldResource`, `PaginatedCaptureFieldsResource`, Controller y `routes/capturefields.php` incluido en `routes/api.php`. Reglas de sistema solo lectura y key única. Verificación: `GET /capture-fields?captureType=pallet` devuelve 12 campos.
4. **`line_fields`: migración y modelo.** `php artisan make:model LineField -m --no-interaction`. FKs, índice único, fillable, casts y relaciones; `lineFields()` en `Line` y `CaptureField`. Verificación: `php artisan migrate` y `database-schema` muestra la tabla.
5. **Feature `LineFields`.** Interface, Service (inyecta `LinesServiceInterface` y `CaptureFieldsServiceInterface`), Provider registrado, Requests, `LineFieldResource`, Controller y rutas en `routes/lines.php`. Reglas de familia, duplicado y calculados obligatorios. Verificación: asignar `boxes` a una línea `pallet` y verlo en `GET /lines/{code}/fields`.
6. **Dependencias de calculados.** Validación al asignar un calculado y al quitar un campo usado por un calculado.
7. **Reglas cruzadas.** Bloqueo de editar `key`/`data_type` y de borrar un campo custom asignado; bloqueo del cambio de `capture_type` en `LinesService::updateLineById`.
8. **Documentación.** Endpoints, schemas `CaptureField` y `LineField`, y `capture_type` en `Line` en `public/openapi.yaml`.
9. **Guía de integración para frontend.** Crear `references/line-capture-fields.md` con la estructura de `references/weekly-plan-task-timeouts.md`: resumen de endpoints, flujo de configuración (familia → asignar campos → formulario), reglas (familia, calculados, dependencias, solo lectura de sistema), tipos TypeScript, cada endpoint con request y respuesta de ejemplo, cómo renderizar cada `data_type`, errores (400, 404, 422 con sus mensajes) y checklist para el frontend.
10. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` agrega `lines.capture_type` con default `pallet` y crea `capture_fields` (único `(capture_type, key)`) y `line_fields` (único `(line_id, capture_field_id)`, FK a `lines` con cascade).
- [ ] Las líneas existentes quedan con `capture_type = 'pallet'`.
- [ ] `php artisan db:seed --class=CaptureFieldsSeeder` ejecutado dos veces deja exactamente 30 filas, todas con `is_system = true`.
- [ ] `php artisan route:list` muestra las 5 rutas de `/capture-fields` y las 4 de `line-fields` / `lines/{code}/fields` bajo `jwt.auth`.
- [ ] `GET /capture-fields?captureType=lot` devuelve los 10 de `lot` más `observations`; sin `limit` es colección y con `limit` es paginado.
- [ ] `POST /capture-fields` crea un campo con `capture_type = null`, `is_system = false`, `is_calculated = false` y responde 201.
- [ ] `POST /capture-fields` con una `key` que ya existe (ej. `boxes` o `observations`) → 400 `Ya existe un campo con la clave {key}`.
- [ ] `POST /capture-fields` con `data_type = select` sin `options`, o con `options` en otro tipo → 422 con mensaje en español.
- [ ] `PATCH` y `DELETE` sobre un campo de sistema → 400 con el mensaje de la spec.
- [ ] `PATCH` de `key` o `data_type` en un campo custom asignado → 400; `PATCH` de `label` u `options` en el mismo campo → 200.
- [ ] `DELETE` de un campo custom asignado → 400 `No se puede eliminar un campo asignado a líneas`; sin asignar → se borra.
- [ ] `POST /lines/{code}/fields` con un campo de otra familia → 400 `El campo no pertenece a la familia de captura de la línea`.
- [ ] `POST /lines/{code}/fields` con un campo global o custom funciona en una línea de cualquier familia.
- [ ] Asignar dos veces el mismo campo a una línea → 400 `El campo ya está asignado a la línea`.
- [ ] Asignar `net_weight` sin `scale_weight` o sin `tare` → 400 que nombra los campos faltantes; con ambos asignados → 201.
- [ ] Asignar o editar un calculado con `is_required = true` → 400 `Un campo calculado no puede ser obligatorio`.
- [ ] Quitar `tare` con `net_weight` asignado → 400 que nombra `Peso neto`; tras quitar `net_weight`, quitar `tare` funciona.
- [ ] `GET /lines/{code}/fields` devuelve los campos ordenados por `order` y luego `id`, con `label` propio si existe o el del catálogo si no.
- [ ] `PATCH /line-fields/{id}` con `label: null` vuelve a la etiqueta del catálogo.
- [ ] `PUT /lines/{code}` con otro `capture_type` en una línea con campos asignados → 400 y la línea conserva su familia; sin campos asignados → se cambia.
- [ ] `capture_type` inválido en `POST` / `PUT /lines` → 422 `La familia de captura no es válida.`
- [ ] Borrar una línea borra sus filas de `line_fields`.
- [ ] `GET /lines/{code}/fields` y `GET /capture-fields` no hacen consultas N+1.
- [ ] `public/openapi.yaml` documenta las 9 rutas nuevas y `capture_type` en `Line`.
- [ ] `references/line-capture-fields.md` existe y cubre los 9 endpoints, los mensajes de error y cómo renderizar cada `data_type`.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** catálogo cerrado de campos de sistema + campos custom + calculadoras en código (opción híbrida). Configurable por línea sin deploy, con fórmulas versionadas y columnas tipadas para reportes.
- **No:** motor de fórmulas en BD (`symfony/expression-language`). Las fórmulas son pocas y estables; agrega errores en runtime y valores en JSON. Se reconsidera si aparecen modalidades nuevas con frecuencia.
- **Sí:** partir el trabajo. SPEC 08 solo configuración; captura y calculadoras en SPEC 09 (`pallet`), 10 (`lot`) y 11 (`product`).
- **Sí:** 3 familias (`pallet`, `lot`, `product`) sacadas del análisis de `templates_excels/`.
- **Sí:** `capture_type` como string + enum PHP, no enum de BD. Agregar una familia no requiere alterar el tipo de la columna.
- **Sí:** default `pallet` en `lines`. Es lo que usan las tomas de rendimiento actuales; las líneas de recorte se corrigen a mano.
- **Sí:** bloquear el cambio de familia si la línea tiene campos. Evita perder configuración por error.
- **Sí:** sembrar desde ya los campos de sistema de las 3 familias. Permite configurar todas las líneas antes de las specs de familia, que pueden agregar campos.
- **Sí:** seeder idempotente (`updateOrCreate`). Se puede volver a correr en cada deploy sin duplicar.
- **Sí:** campos de sistema de solo lectura por API. Una calculadora depende de su `key` y su tipo.
- **Sí:** campos custom siempre globales, de entrada y sin dependencias. Un custom no entra en cálculos (se guardará en `extra_values`).
- **Sí:** `key` y `data_type` de un custom editables solo mientras no esté asignado; `label` y `options` siempre.
- **Sí:** `key` de un custom única en todo el catálogo. Evita choques con campos de sistema y entre custom globales (el índice único no cubre `null` en `pgsql`).
- **Sí:** `observations` como campo de sistema global. Aparece en todas las familias.
- **No:** `scheduled_boxes` (cajas programadas) como campo de captura. Es dato de la tarea (`weekly_plan_tasks.boxes`), no de cada tarima; el frontend lo muestra desde la tarea.
- **Sí:** tipos `number`, `integer`, `text`, `date`, `time`, `boolean`, `select`. Cubren todo lo que aparece en los Excel.
- **Sí:** `status` de `pallet` como `select` con `APROBADO` / `RECHAZADO`.
- **Sí:** configuración por línea, no por línea + SKU. Coincide con los Excel (una hoja por línea).
- **Sí:** CRUD por campo en `line_fields` en lugar de sync completo.
- **Sí:** calculados asignables, mostrados como solo lectura, nunca obligatorios y validando que sus dependencias estén asignadas.
- **Sí:** bloquear quitar un campo usado por un calculado asignado. Mismo criterio que la asignación.
- **Sí:** bloquear borrar un custom asignado. Mismo criterio que borrar un `Timeout` en uso (SPEC 07).
- **Sí:** `label` propio por línea, opcional. Las hojas usan nombres distintos para el mismo dato (ej. "Lote" / "Lote (GRN)").
- **Sí:** `order` no único. Simplifica reordenar; los empates se resuelven por `id`.
- **Sí:** línea identificada por `code` en `/lines/{code}/fields`, igual que `/lines/{line}`.
- **Sí:** rutas de `LineFields` en `routes/lines.php`, como sub-recurso de la línea.
- **No:** restringir a admin. Se pidió cualquier usuario autenticado, igual que el resto de las features.
- **No:** `capture_type` en la carga masiva por Excel. Las líneas cargadas quedan en `pallet`.
- **No:** fórmulas de calculados en esta spec. Solo se fija `depends_on`; la fórmula y el signo del diferencial se definen en cada spec de familia.
- **Sí:** carpeta nueva `app/Enums/`. No existía; es la ubicación estándar en Laravel.
- **No:** tests automatizados. Consistente con SPEC 01–07; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| El seeder no se corre en producción y el catálogo queda vacío. | Paso de deploy: `php artisan db:seed --class=CaptureFieldsSeeder`. Es idempotente. |
| Líneas de recorte quedan como `pallet` y se configuran con campos equivocados. | Corregir `capture_type` antes de asignar campos; después de asignar, el cambio queda bloqueado hasta vaciar la configuración. |
| La lista de campos de sistema no cubre algo que aparezca después. | Las specs de familia pueden agregar campos al seeder. Si el dato no entra en cálculos, se crea como custom sin deploy. |
| `depends_on` sembrado no coincide con la fórmula final de la spec de familia. | La spec de familia corrige `depends_on` en el seeder; al ser idempotente, se actualiza en el siguiente deploy. |
| Las tomas de rendimiento actuales (`weekly_plan_task_performance_records`) no usan esta configuración. | Se acepta hasta SPEC 09, que las adapta a la familia `pallet`. |
| Las opciones de `status` (`APROBADO` / `RECHAZADO`) no son todas las que usa planta. | Se corrigen en el seeder. Si varían por línea, se modela en SPEC 09. |
| Dos usuarios asignan un calculado y quitan su dependencia al mismo tiempo. | Se acepta: es configuración poco frecuente. La captura de cada familia vuelve a validar las entradas antes de calcular. |

---

## Lo que **no** está en esta spec

- Captura de registros y guardado de valores por familia.
- Calculadoras y fórmulas de los campos calculados.
- Adaptar las tomas de rendimiento actuales.
- Constantes por SKU o LineSku.
- Módulos extra (HPP, tanque/prensa, tiempos muertos por área).
- Configuración por línea + SKU.
- Copiar configuración entre líneas.
- `capture_type` en la carga masiva de líneas.
- Restricción a admin.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
