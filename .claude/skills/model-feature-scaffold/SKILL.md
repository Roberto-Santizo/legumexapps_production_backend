---
name: model-feature-scaffold
description: "Use this skill to scaffold a full CRUD API feature in this Laravel backend starting from an existing model in app/Models (or a new model the user describes). Triggers when the user asks to 'create a feature for <Model>', 'add CRUD for <Model>', 'expose <Model> via API', or asks to finish a half-built resource (model + migration exist but no controller/service/routes yet). Generates, in the project's own layered convention, the Interface, Service, Provider, FormRequests, Resources, Controller, and route file for the model, and wires them into bootstrap/providers.php and routes/api.php. Do not use for plain Eloquent tweaks, unrelated bug fixes, or frontend work."
metadata:
  author: project
---

# Model → Feature Scaffold

This backend does **not** use plain Laravel controllers. Every resource is built as a small
layered stack: `Model → Interface → Service → Provider → FormRequests → Resources → Controller → Routes`.
Before generating anything, study the 2-3 existing features that are structurally closest to the
target model (same relation shape) and mirror their exact conventions — this codebase is **not**
perfectly uniform between features (see "Known inconsistencies" below), so copy the closest sibling
rather than assuming one universal rule.

## Step 0 — Inspect the target model

Read `app/Models/{Model}.php` and note:

- **Fillable fields** — declared via `#[Fillable([...])]` attribute above the class (Laravel 13 style),
  not a `protected $fillable` property.
- **Relations** — `belongsTo()` methods reveal foreign keys (e.g. `weekly_plan_id`, `line_sku_id`).
- **`use HasFactory;`** — present only on some models. Only create a factory if the model uses it
  (or add the trait if the user wants one and it's missing).
- **A unique business identifier** — some resources are looked up in the URL by a `code` column
  instead of the numeric `id` (e.g. `PackingMaterial`, `Sku`, `Line`). Check the migration for a
  `->unique()` string column named `code` to decide whether the service needs a `getXByCode()` method.

Then check whether a migration already exists for the table (`database/migrations/*create_{table}*`).
If the model and migration already exist (common when a feature was started but left unfinished),
skip straight to Step 2.

## Step 1 — Migration (only if missing)

Create with `php artisan make:migration create_{snake_plural}_table --no-interaction`, then fill the
`Schema::create` block to match every field in the model's `#[Fillable]` list, plus `$table->id()` and
`$table->timestamps()`. Foreign keys use `$table->foreignId('{relation}_id')->constrained()` (add
`->on('{other_table}')` when the column name doesn't match the referenced table's singular, as in
`weekly_plan_tasks` → `line_sku_id` referencing `line_stock_keeping_units`). Nullable/optional columns
use `->nullable()`.

## Step 2 — Factory (only if the model uses `HasFactory`)

`php artisan make:factory {Model}Factory --no-interaction`. In `definition()`, set every required
fillable field; for foreign keys use `{Related}::factory()` (see
`database/factories/WeeklyPlanEmployeeFactory.php` for the pattern with three FKs).

## Step 3 — Interface

`app/Interfaces/{Plural}/{Plural}ServiceInterface.php`:

```php
<?php

namespace App\Interfaces\{Plural};

interface {Plural}ServiceInterface
{
    public function create{Model}(array $data);

    public function get{Plural}(?string $limit);

    public function get{Model}ById(string $id);

    // Only if the model has a unique `code` column looked up from the URL:
    // public function get{Model}ByCode(string $code);

    public function update{Model}ById(string $id, array $data);

    public function delete{Model}ById(string $id);
}
```

Check the closest sibling interface for **argument order on the update method** — this codebase has
both `updateXById(string $id, array $data)` (e.g. `PackingMaterialsServiceInterface`,
`ClientsServiceInterface`) and `updateXById(array $data, string $id)` (e.g. `PositionsServiceInterface`,
`WeeklyPlanEmployeesServiceInterface`). Pick whichever the nearest analogous feature uses, and keep the
Service and Controller calls consistent with the interface you write.

## Step 4 — Service

`app/Services/{Plural}/{Plural}Service.php`, implementing the interface, with `#[Override]` on every
method (import `use Override;`):

```php
<?php

namespace App\Services\{Plural};

use App\Errors\NotFoundError;
use App\Interfaces\{Plural}\{Plural}ServiceInterface;
use App\Models\{Model};
use Override;

class {Plural}Service implements {Plural}ServiceInterface
{
    #[Override]
    public function create{Model}(array $data)
    {
        return {Model}::create($data);
    }

    #[Override]
    public function get{Plural}(?string $limit)
    {
        $query = {Model}::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function get{Model}ById(string $id)
    {
        ${model} = {Model}::find($id);
        if (! ${model}) {
            throw new NotFoundError('El {nombre en español} no existe');
        }

        return ${model};
    }

    #[Override]
    public function update{Model}ById(string $id, array $data)
    {
        ${model} = $this->get{Model}ById($id);
        ${model}->update($data);

        return true;
    }

    #[Override]
    public function delete{Model}ById(string $id)
    {
        ${model} = $this->get{Model}ById($id);
        ${model}->delete();

        return true;
    }
}
```

If the model has a business `code` lookup, add `get{Model}ByCode(string $code)` using
`{Model}::where('code', '=', $code)->first()`, and have `show`/`update`/`destroy` in the controller call
that instead of the by-id lookup (see `PackingMaterialsService`).

Error messages are always **Spanish**, phrased "El/La {recurso} no existe". Use `NotFoundError` (404)
for missing records and `BadRequestError` (400) from `App\Errors` for validation-style failures inside
the service (e.g. bulk import row errors) — both extend `App\Errors\ApiException`.

## Step 5 — Provider

`app/Providers/{Plural}/{Plural}Provider.php`:

```php
<?php

namespace App\Providers\{Plural};

use App\Interfaces\{Plural}\{Plural}ServiceInterface;
use App\Services\{Plural}\{Plural}Service;
use Illuminate\Support\ServiceProvider;

class {Plural}Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind({Plural}ServiceInterface::class, {Plural}Service::class);
    }

    public function boot(): void
    {
        //
    }
}
```

Register it in `bootstrap/providers.php`: add the `use` import and append `{Plural}Provider::class,` to
the returned array (keep the list roughly alphabetical, matching current order).

## Step 6 — FormRequests

`app/Http/Requests/{Plural}/Create{Model}Request.php` and `Update{Model}Request.php`. Both have
`authorize(): bool` (return `true` unless the closest sibling restricts by role — see
`CreatePositionRequest` which blocks non-`admin` users; ask the user if unsure whether this resource
needs that), `rules(): array`, and `messages(): array` with **Spanish** messages for every rule.

- Foreign key fields: `['required', 'integer', 'exists:{table},id']`.
- A unique `code` field on create: `['required', 'string', 'unique:{table},code']`.
- On update, the same field uses `Rule::unique('{table}', 'code')->ignore($this->route('{singular_snake_uri_param}'), 'code')`
  — the route param name is the singular snake_case of the `apiResource` URI segment (e.g. URI
  `/packing-materials` → route param `packing_material`).
- Booleans: `['nullable', 'boolean']` (or `['boolean']` if not optional — match sibling).

## Step 7 — Resources

`app/Http/Resources/{Plural}/{Model}Resource.php` — plain `toArray()` mapping the exposed fields
(never dump raw `$this->getAttributes()`; list fields explicitly, casting booleans with `? true : false`
if stored as tinyint).

`app/Http/Resources/{Plural}/Paginated{Plural}Resource.php` — wraps a paginator:

```php
public function toArray(Request $request): array
{
    $items = {Model}Resource::collection($this->items());

    return [
        'data' => $items,
        'total' => $this->total(),
        'currentPage' => $this->currentPage(),
        'lastPage' => $this->lastPage(),
    ];
}
```

## Step 8 — Controller

`app/Http/Controllers/{Plural}Controller.php`. Every action wraps its body in `try { ... } catch
(\Throwable $th) { return ResponseHandler::error($th); }`. Success responses use
`ResponseHandler::success($data, '{Mensaje en español}', {statusCode})` — `201` for `store`, `200`
otherwise. `index` chooses between the paginated resource and the plain collection based on
`$request->query('limit')`, exactly like `PackingMaterialsController::index`. Inject
`{Plural}ServiceInterface` (not the concrete service) as a controller-method parameter — this project
uses method injection, not constructor injection.

## Step 9 — Routes

Decide whether this resource belongs inside an **existing** domain route file (e.g. `routes/skus.php`
already groups `SkusController`, `LineSkusController`, and `SkuPackingMaterialsController` together) or
needs its own new file. If new, create `routes/{lowercasenospaces}.php`:

```php
<?php

use App\Http\Controllers\{Plural}Controller;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::apiResource('/{kebab-plural}', {Plural}Controller::class);
});
```

Then add `require __DIR__.'/{lowercasenospaces}.php';` to `routes/api.php` (append at the bottom,
matching existing require order). If adding to an existing file instead, just append the
`Route::apiResource(...)` line inside the existing `jwt.auth` group.

## Step 10 — Finish

1. Run `vendor/bin/pint --dirty --format agent` to fix formatting on every file you touched.
2. Sanity-check with `php artisan route:list --path={kebab-plural}`.
3. Do **not** scaffold Pest tests unless the user asks — this project currently has no `tests/`
   directory at all, so there's no existing precedent to mirror. If the user does want tests, invoke
   the `pest-testing` skill first.

## Known inconsistencies to watch for (don't "fix" them, just match locally)

- `update{Model}ById` argument order (`$id, $data` vs `$data, $id`) differs between existing features.
- Lookup key for `show`/`update`/`destroy` is sometimes the numeric `id`, sometimes a business `code`
  column — depends on the model.
- Most route files are named after a single resource, but a few (`routes/skus.php`) intentionally group
  several related resources under one domain file.
