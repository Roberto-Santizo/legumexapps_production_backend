# SPEC 05 — Firmas de transacciones de material de empaque como imágenes en S3

> **Estado:** Implementado
> **Depende de:** —
> **Fecha:** 2026-09-30
> **Objetivo:** Que `POST` de `PackingMaterialTransaction` reciba `responsable_signature` y `user_signature` como imágenes PNG, las suba con ACL pública a un bucket S3 y guarde en BD solo la key de cada archivo.

---

## Por qué existe esta spec

Hoy las firmas llegan como `string` y se guardan tal cual en columnas `varchar(255)`. Una firma dibujada en canvas no cabe ahí como base64 y no tiene sentido guardarla en BD.

La firma pasa a ser un archivo: el frontend la manda como `multipart/form-data`, el backend la sube a S3 y guarda la key. El `Resource` arma la URL pública al leer.

De paso se corrige un bug de `createPackingMaterialTransaction`: hace `$task->status = 2` aunque `weekly_plan_task_id` es `nullable`, así que una transacción sin tarea truena con error sobre `null`.

---

## Alcance

**Dentro:**

- `CreatePackingMaterialTransactionRequest`: `responsable_signature` y `user_signature` pasan a ser archivos PNG obligatorios de máximo 1 MB.
- `PackingMaterialTransactionsService::createPackingMaterialTransaction`: sube ambas imágenes al disco `s3` con `visibility => public` explícito (ACL `public-read`) y guarda la key en BD.
- Keys con nombre UUID: `packing-material-transactions/signatures/{uuid}.png`.
- Comportamiento todo o nada: si falla la subida o la BD, rollback y borrado de S3 de los archivos ya subidos.
- `PackingMaterialTransactionResource`: `responsable_signature` y `user_signature` devuelven la URL pública (`Storage::disk('s3')->url($key)`).
- `store` responde `data: null` (mismo mensaje y código 201).
- Corrección del bug: el status de la `WeeklyPlanTask` solo se actualiza si `weekly_plan_task_id` viene informado.
- `UpdatePackingMaterialTransactionRequest`: se eliminan `responsable_signature` y `user_signature`. Las firmas no se pueden actualizar.

**Fuera de alcance (para specs futuras):**

- Instalar `league/flysystem-aws-s3-v3`. Lo hace el usuario a mano; esta spec asume que ya está instalado.
- Configurar el bucket, credenciales AWS o `.env`. El disco `s3` de `config/filesystems.php` ya existe y no se toca.
- Reemplazar firmas en `update`.
- Borrar las imágenes de S3 en `destroy`. Quedan en el bucket.
- Bucket privado o URLs firmadas (`temporaryUrl`).
- Migrar datos existentes. No hay transacciones reales con el formato viejo.
- Cambiar el tipo de las columnas. `varchar(255)` alcanza para la key.
- Otros formatos de imagen (jpg, webp) o base64 en JSON.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

Sin cambios de esquema ni de modelo. Cambia el **contenido** de dos columnas existentes de `packing_material_transactions`:

| Columna                 | Antes              | Ahora                                                        |
| ----------------------- | ------------------ | ------------------------------------------------------------ |
| `responsable_signature` | string libre       | key S3: `packing-material-transactions/signatures/{uuid}.png` |
| `user_signature`        | string libre       | key S3: `packing-material-transactions/signatures/{uuid}.png` |

Cada firma tiene su propio UUID (`Str::uuid()`), generado antes de crear el registro. No depende del `id` de la transacción.

### Request (`multipart/form-data`)

```text
reference                 string   required
responsable               string   required
observations              string   nullable
responsable_signature     file     required|image|mimes:png|max:1024
user_signature            file     required|image|mimes:png|max:1024
type                      integer  required
weekly_plan_task_id       integer  nullable|exists:weekly_plan_tasks,id
items[0][quantity]        numeric  required
items[0][lote]            string   required
items[0][destination]     string   nullable
items[0][packing_material_id] integer required|exists:packing_materials,id
```

Mensajes en español para las reglas nuevas, siguiendo el estilo actual:

- `responsable_signature.image` → `La firma del responsable debe ser una imagen.`
- `responsable_signature.mimes` → `La firma del responsable debe ser un archivo PNG.`
- `responsable_signature.max` → `La firma del responsable no debe pesar más de 1 MB.`
- Ídem para `user_signature` con `La firma del usuario ...`.
- Se eliminan los mensajes `*.string` de ambas firmas.

### Subida

```php
Storage::disk('s3')->putFileAs(
    'packing-material-transactions/signatures',
    $file,
    Str::uuid().'.png',
    ['visibility' => 'public'],
);
```

`visibility => public` se pasa en cada llamada, no en la config del disco. Flysystem lo traduce a ACL `public-read`.

### Respuesta del Resource

```php
'responsable_signature' => Storage::disk('s3')->url($this->responsable_signature),
'user_signature' => Storage::disk('s3')->url($this->user_signature),
```

---

## Plan de implementación

1. **Request de creación.** En `app/Http/Requests/PackingMaterialTransactions/CreatePackingMaterialTransactionRequest.php` cambiar las reglas de ambas firmas a `['required', 'image', 'mimes:png', 'max:1024']` y actualizar sus mensajes. Prueba manual: POST con firma en texto → 422 con `La firma del responsable debe ser una imagen.`
2. **Subida a S3 en el Service.** En `PackingMaterialTransactionsService::createPackingMaterialTransaction`, antes del `DB::transaction`, subir ambos archivos con `putFileAs` + `['visibility' => 'public']` y reemplazar `$data['responsable_signature']` y `$data['user_signature']` por las keys devueltas. Prueba manual: POST válido → dos objetos nuevos en el bucket y keys en BD.
3. **Todo o nada.** Envolver la subida y el `DB::transaction` en `try/catch (\Throwable)`. En el `catch`, borrar de S3 las keys ya subidas (`Storage::disk('s3')->delete(...)`) y relanzar la excepción. Prueba manual: forzar un `packing_material_id` que haga fallar un insert → sin registros en BD y sin archivos nuevos en el bucket.
4. **Bug de la tarea.** Mover la actualización de status de la `WeeklyPlanTask` dentro del `DB::transaction` y ejecutarla solo si `weekly_plan_task_id` viene informado. Prueba manual: POST sin `weekly_plan_task_id` → 201.
5. **Respuesta de store.** El Service devuelve `null`. `PackingMaterialTransactionsController::store` responde `ResponseHandler::success(null, 'Transacción de Material de Empaque Creada Correctamente', 201)`.
6. **Resource.** En `PackingMaterialTransactionResource`, devolver `Storage::disk('s3')->url(...)` para ambas firmas. Prueba manual: `GET /…/{id}` → URLs que abren la imagen en el navegador sin autenticación.
7. **Request de actualización.** En `UpdatePackingMaterialTransactionRequest` eliminar las reglas y mensajes de `responsable_signature` y `user_signature`.
8. Correr `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [X] POST con `multipart/form-data` y dos PNG válidos responde 201 con `data: null`.
- [X] Tras ese POST existen dos objetos nuevos en `packing-material-transactions/signatures/` con nombre `{uuid}.png`.
- [X] Ambos objetos tienen ACL `public-read` (visible en la consola de S3 o con `GetObjectAcl`).
- [ ] Las columnas `responsable_signature` y `user_signature` del registro guardan la key, no la URL.
- [X] `GET` de la transacción devuelve en ambas firmas una URL que abre la imagen sin credenciales.
- [X] POST con una firma como texto responde 422 con el mensaje de imagen.
- [X] POST con una firma JPG responde 422 con `... debe ser un archivo PNG.`
- [X] POST con una firma PNG de más de 1 MB responde 422 con `... no debe pesar más de 1 MB.`
- [X] POST sin `weekly_plan_task_id` responde 201 (antes fallaba).
- [X] POST con `weekly_plan_task_id` deja la tarea en `status = 2`.
- [X] Si falla la creación en BD tras subir las imágenes, no queda registro en BD ni archivos nuevos en el bucket.
- [X] PUT/PATCH que envía `responsable_signature` o `user_signature` no modifica esas columnas.

---

## Decisiones

- **Sí:** `multipart/form-data` con `UploadedFile`. Validación nativa de Laravel (`image`, `mimes`, `max`).
- **No:** base64 en JSON. Obliga a decodificar y validar el tipo a mano.
- **Sí:** bucket público con URL fija. Decisión del usuario.
- **Sí:** `visibility => public` explícito en cada `putFileAs`. El usuario quiere que la ACL pública sea explícita, no implícita por config del disco.
- **No:** bucket privado con `temporaryUrl`. Descartado por el usuario.
- **Sí:** guardar la key, no la URL. Cambiar dominio o poner CDN no requiere migrar datos.
- **Sí:** nombre UUID. Se sube antes de crear el registro, sin depender del `id`.
- **No:** ruta por id (`{id}/responsable_signature.png`). Obligaría a crear el registro antes de subir.
- **Sí:** solo PNG, máximo 1 MB. Una firma de canvas es PNG y pesa mucho menos.
- **Sí:** todo o nada con limpieza de S3 en el `catch`. Evita archivos huérfanos.
- **Sí:** `store` devuelve `null`. El frontend no usa la respuesta.
- **Sí:** corregir el bug de `$task` nulo aquí. Toca el mismo método.
- **Sí:** mover el update de status de la tarea dentro del `DB::transaction`. Queda dentro del todo o nada.
- **Sí:** las firmas no se pueden actualizar. Se quitan del `UpdateRequest`.
- **No:** instalar `league/flysystem-aws-s3-v3` en el plan. El usuario lo instala a mano.
- **No:** borrar imágenes en `destroy`. Fuera de alcance.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| El bucket tiene *Object Ownership* en `Bucket owner enforced` (ACLs deshabilitadas, default en buckets nuevos de AWS). `putFileAs` con `visibility => public` falla con `AccessControlListNotSupported`. | Habilitar ACLs en el bucket (`Bucket owner preferred` / `Object writer`) y desactivar el *Block Public Access* correspondiente antes de probar. |
| `league/flysystem-aws-s3-v3` no instalado. El disco `s3` lanza error al primer uso. | El usuario lo instala antes de implementar. |
| Falla la limpieza de S3 en el `catch` (red, permisos). | Se relanza la excepción original igual. Quedan archivos huérfanos; se acepta. |
| El frontend sigue mandando JSON. | Todas las peticiones fallan con 422 hasta que se migre a `FormData`. Coordinar el despliegue con el frontend. |

---

## Lo que **no** entra en esta spec

- Instalación de `league/flysystem-aws-s3-v3` y configuración de AWS.
- Reemplazo de firmas en `update`.
- Borrado de imágenes en `destroy`.
- Bucket privado o URLs firmadas.
- Migración de datos o cambio de tipo de columnas.
- Formatos distintos de PNG o base64.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
