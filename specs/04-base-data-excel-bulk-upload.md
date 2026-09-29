# SPEC 04 — Carga masiva por Excel de la información base

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-09-29
> **Objetivo:** Permitir crear en lote registros de `Line`, `Position`, `Client`, `Timeout`, `RawMaterial`, `PackingMaterial` y `LineSku` subiendo un archivo Excel por recurso, validando todas las filas y guardando todo o nada.

---

## Por qué existe esta spec

Hoy la información base se captura registro por registro con el CRUD de cada recurso. Al arrancar una planta o una temporada eso son cientos de altas manuales.

El proyecto ya tiene un patrón probado de carga por Excel en `WeeklyPlanEmployeesService::uploadFile`: `Maatwebsite\Excel` con `WithHeadingRow`, resolución de FKs por código con `pluck('id', 'code')`, errores acumulados por número de línea, `BadRequestError` si hay alguno e `insert` dentro de `DB::transaction`. **Esta spec replica ese patrón en siete recursos**, sin abstracciones nuevas.

---

## Alcance

**Dentro:**

- Un endpoint `POST /{recurso}/uploadFile` por cada uno de los siete recursos, en su archivo de rutas actual.
- Una clase `App\Imports\{Recurso}Import` por recurso, que solo implementa `WithHeadingRow`, igual que `WeeklyPlanEmployeesImport`.
- Un método `uploadFile(mixed $file)` en cada `*ServiceInterface` y su `*Service`.
- Un método `uploadFile(UploadFileRequest $request, ...)` en cada controlador, reutilizando `App\Http\Requests\Shared\UploadFileRequest`.
- Validación por fila: campos requeridos, tipos numéricos, FKs por código y duplicados contra BD y dentro del mismo archivo.
- Comportamiento todo o nada: si una fila falla, no se inserta ninguna.
- Rechazo de archivos sin filas de datos.
- Migración que agrega el índice único `(sku_id, line_id)` a `line_stock_keeping_units`.

**Fuera de alcance (para specs futuras):**

- Actualizar registros existentes (upsert). Un código o nombre existente es error, no actualización.
- Inserción parcial. No se insertan las filas válidas si hay alguna inválida.
- Endpoint para descargar la plantilla Excel vacía. Las columnas quedan documentadas en esta spec.
- Endpoint genérico `POST /uploads/{entidad}`.
- Columnas opcionales con default en BD (`packing_materials.blocked`, `positions.status`, `line_stock_keeping_units.status`). Toman su default; el Excel no las lee.
- Resolver referencias contra filas del mismo archivo. Una posición solo puede apuntar a una línea que **ya existe en BD**, no a una que viene en otro Excel ni en la misma carga.
- Carga masiva de `Sku`, `LineDependency`, `SkuRawMaterial`, `SkuPackingMaterial` u otros modelos no listados.
- Carga encolada o asíncrona. El procesamiento es síncrono, igual que hoy.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `add_unique_sku_line_to_line_stock_keeping_units_table`

```php
Schema::table('line_stock_keeping_units', function (Blueprint $table) {
    $table->unique(['sku_id', 'line_id']);
});
```

`down()` elimina el índice. Es el único cambio de esquema. Los modelos no cambian.

### Columnas del Excel por recurso

La primera fila es el encabezado. `WithHeadingRow` lo convierte a slug, así que `Código` y `codigo` son equivalentes. Todas las columnas listadas son obligatorias.

| Recurso           | Ruta                                 | Columnas del Excel                                                   | Mapeo a BD                                                                            |
| ----------------- | ------------------------------------ | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| `Line`            | `POST /lines/uploadFile`             | `nombre`, `codigo`, `turno`                                          | `name`, `code`, `shift`                                                               |
| `Position`        | `POST /positions/uploadFile`         | `codigo`, `actividad`, `linea`                                       | `code`, `activity`, `line_id` (por `lines.code`)                                      |
| `Client`          | `POST /clients/uploadFile`           | `nombre`                                                             | `name`                                                                                |
| `Timeout`         | `POST /timeouts/uploadFile`          | `nombre`                                                             | `name`                                                                                |
| `RawMaterial`     | `POST /raw-materials/uploadFile`     | `codigo`, `nombre_producto`                                          | `code`, `product_name`                                                                |
| `PackingMaterial` | `POST /packing-materials/uploadFile` | `codigo`, `nombre`, `descripcion`                                    | `code`, `name`, `description`                                                         |
| `LineSku`         | `POST /performances/uploadFile`      | `sku`, `linea`, `rendimiento_lbs`, `porcentaje_aceptado`, `metodo_pago` | `sku_id` (por `stock_keeping_units.code`), `line_id` (por `lines.code`), `lbs_performance`, `accepted_percentage`, `payment_method` |

### Reglas de validación por fila

- Todo valor se castea a `string` y se le aplica `trim` antes de validar. Excel convierte códigos como `001` en números; el cast evita comparar `1` con `"001"` de forma distinta a como está en BD.
- **Requeridos:** celda vacía tras `trim` → `Línea N: el campo '<columna>' es obligatorio`.
- **Numéricos:** `turno`, `rendimiento_lbs` y `porcentaje_aceptado` deben pasar `is_numeric` → `Línea N: '<columna>' debe ser numérico`.
- **Booleano `metodo_pago`:** acepta `1`, `0`, `SI`, `NO` (case-insensitive, `SÍ` también cuenta como `SI`). `1`/`SI` → `true`, `0`/`NO` → `false`. Cualquier otro valor es error.
- **FK por código:** comparación exacta contra `pluck('id', 'code')`. Código inexistente → `Línea N: la línea con código 'X' no existe` / `el sku con código 'X' no existe`.
- **Duplicado por código** (`Line`, `Position`, `RawMaterial`, `PackingMaterial`): comparación exacta. Si existe en BD → `Línea N: el código 'X' ya existe`. Si se repite dentro del archivo → `Línea N: el código 'X' está repetido en el archivo`.
- **Duplicado por nombre** (`Client`, `Timeout`): comparación case-insensitive tras `trim`, contra BD y dentro del archivo, con los mismos mensajes cambiando "código" por "nombre".
- **Duplicado de par** (`LineSku`): el par `(sku_id, line_id)` no puede existir en BD ni repetirse en el archivo → `Línea N: el sku 'X' ya está asignado a la línea 'Y'`.
- `N` es `$index + 2`, igual que el patrón actual (fila 1 es el encabezado).

### Respuesta

- Éxito: `ResponseHandler::success(['created' => <int>], 'Archivo Subido Correctamente', 200)`.
- Errores de filas: `BadRequestError` con todos los mensajes unidos por `PHP_EOL`.
- Archivo sin filas de datos: `BadRequestError('El archivo no contiene filas')`.
- Archivo inválido (extensión, faltante): lo rechaza `UploadFileRequest` antes de llegar al servicio.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración de unicidad de LineSku.** `php artisan make:migration add_unique_sku_line_to_line_stock_keeping_units_table`. Verificación: `php artisan migrate` corre sin error y `database-schema` muestra el índice.
2. **Clients.** `app/Imports/ClientsImport.php`; `uploadFile` en `ClientsServiceInterface`, `ClientsService` y `ClientsController`; ruta `POST /clients/uploadFile` en `routes/clients.php`, declarada **antes** del `apiResource`. Es el recurso más simple y sirve de plantilla para el resto. Verificación manual con un Excel válido, uno con nombre duplicado y uno vacío.
3. **Timeouts.** Igual que el paso 2 en `routes/timeouts.php`.
4. **RawMaterials.** Import, interface, service, controller y ruta en `routes/bodega.php`. Introduce el duplicado por código.
5. **PackingMaterials.** Igual que el paso 4 en `routes/bodega.php`.
6. **Lines.** Import, interface, service, controller y ruta en `routes/lines.php`. Introduce la validación numérica (`turno`).
7. **Positions.** Import, interface, service, controller y ruta en `routes/lines.php`. Introduce la FK `linea` → `lines.code`.
8. **LineSkus.** Import, interface, service, controller y ruta `POST /performances/uploadFile` en `routes/skus.php`. Introduce dos FKs, el booleano `metodo_pago` y el duplicado de par.
9. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea el índice único `(sku_id, line_id)` en `line_stock_keeping_units`.
- [ ] `php artisan route:list --path=uploadFile` muestra las 7 rutas nuevas más la existente de `weekly-plan-employees`, todas bajo `jwt.auth`.
- [ ] Subir un `.csv` o no enviar `file` responde el error de validación de `UploadFileRequest`.
- [ ] Un Excel válido de cada recurso responde 200 con `created` igual al número de filas de datos, y los registros existen en BD.
- [ ] Un Excel con solo el encabezado responde `El archivo no contiene filas` y no inserta nada.
- [ ] Un Excel con una fila inválida entre varias válidas responde error con el número de línea correcto y **no** inserta ninguna fila.
- [ ] Un Excel con varias filas inválidas devuelve **todos** los errores en una sola respuesta, no solo el primero.
- [ ] Un `codigo` que ya existe en BD (lines, positions, raw_materials, packing_materials) produce `Línea N: el código 'X' ya existe`.
- [ ] Un `codigo` repetido dos veces en el mismo archivo produce error en la segunda aparición.
- [ ] Un cliente `ACME` en BD hace fallar una fila con `nombre` = ` acme ` (espacios y minúsculas).
- [ ] Una posición con `linea` inexistente produce `Línea N: la línea con código 'X' no existe`.
- [ ] Un LineSku con par `(sku, linea)` ya existente en BD, o repetido en el archivo, produce error.
- [ ] `metodo_pago` acepta `1`, `0`, `SI`, `si`, `NO`, `Sí` y rechaza `2`, `X` o vacío.
- [ ] `turno`, `rendimiento_lbs` o `porcentaje_aceptado` con texto no numérico producen error de línea.
- [ ] Un código numérico como `001` en Excel se guarda y compara como string.
- [ ] Los registros creados con `blocked`/`status` quedan con el default de BD.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** replicar el patrón de `WeeklyPlanEmployeesService::uploadFile`. Ya está en producción y es el que conoce el equipo.
- **No:** clase base o trait genérico de importación. Siete métodos parecidos son más legibles que una abstracción parametrizada por reglas; si aparece un octavo, se reevalúa.
- **Sí:** un endpoint por recurso (`POST /{recurso}/uploadFile`). Sigue la convención existente y cada ruta vive junto a su `apiResource`.
- **No:** endpoint genérico `POST /uploads/{entidad}`. Rompe la organización por archivo de rutas y mezcla responsabilidades en un solo controlador.
- **Sí:** todo o nada. El usuario corrige el Excel y lo vuelve a subir entero, sin tener que averiguar qué filas ya entraron.
- **No:** inserción parcial. Obliga a re-subir solo las filas fallidas, fuente habitual de duplicados.
- **Sí:** código o nombre existente es error. Una carga masiva no debe modificar datos en silencio.
- **No:** upsert ni omitir filas existentes.
- **Sí:** nombres de `Client` y `Timeout` únicos case-insensitive a nivel de carga. Evita `ACME` y `Acme` como dos clientes.
- **No:** índice unique en BD para `clients.name` / `timeouts.name`. El CRUD actual permite duplicados y no se toca en esta spec.
- **Sí:** índice unique `(sku_id, line_id)` en `line_stock_keeping_units`. Refuerza en BD lo que la carga valida.
- **Sí:** encabezados en español y FKs por código. El Excel lo llena personal de planta, que conoce códigos, no ids.
- **Sí:** el Excel lleva solo los campos del `Create*Request` de cada recurso. Los flags con default (`blocked`, `status`) no se cargan.
- **Sí:** `metodo_pago` acepta `1/0` y `SI/NO`. Es la forma natural de escribirlo en Excel.
- **Sí:** archivo vacío es `BadRequestError`. Un 200 con `created: 0` oculta que se subió el archivo equivocado.
- **No:** endpoint de plantilla descargable. Las columnas quedan documentadas aquí; otra spec si hace falta.
- **No:** tests automatizados. Consistente con SPEC 01–03; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| --- | --- |
| La migración unique falla si ya existen pares `(sku_id, line_id)` duplicados en `line_stock_keeping_units`. | Antes de migrar, revisar con `database-query` un `GROUP BY sku_id, line_id HAVING COUNT(*) > 1`. Si hay filas, se depuran a mano antes del paso 1. |
| El CRUD de `LineSku` (`CreateLineSkuRequest`) no valida el par y ahora chocará con el índice, devolviendo un error SQL en vez de un mensaje claro. | Queda registrado. Agregar la regla de unicidad al `CreateLineSkuRequest` va fuera de esta spec. |
| Excel convierte códigos numéricos (`001` → `1`) antes de llegar al servidor. | El cast a string no recupera ceros perdidos. Se documenta al usuario que formatee la columna como texto. |
| Archivos muy grandes (miles de filas) ralentizan la petición síncrona. | Las validaciones usan mapas precargados (`pluck`) y un solo `insert`, sin consultas por fila. Carga encolada queda fuera. |
| Un `insert` masivo no dispara eventos de modelo ni observers. | Ninguno de los siete modelos tiene observer hoy. Si se agrega uno, habrá que revisar esta carga. |

---

## Lo que **no** está en esta spec

- Upsert, omisión de existentes o inserción parcial.
- Plantilla Excel descargable.
- Endpoint genérico de carga.
- Carga de `Sku`, `LineDependency`, `SkuRawMaterial`, `SkuPackingMaterial` u otros modelos.
- Columnas `blocked` / `status` en el Excel.
- Referencias entre filas de la misma carga o entre cargas simultáneas.
- Procesamiento encolado.
- Validación de unicidad del par en `CreateLineSkuRequest`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
