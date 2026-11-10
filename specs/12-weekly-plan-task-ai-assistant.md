# SPEC 12 — Asistente de consulta IA para tareas del plan semanal

> **Estado:** Aprobado
> **Depende de:** SPEC 01, SPEC 03, SPEC 06, SPEC 07, SPEC 08, SPEC 09, SPEC 10, SPEC 11
> **Fecha:** 2026-11-10
> **Objetivo:** Exponer un agente de Laravel AI SDK, de solo lectura y con historial por usuario, que responde en streaming preguntas sobre las `WeeklyPlanTask` usando tools que consultan y resumen sus datos, con énfasis en las tomas de rendimiento por familia (`pallet`, `lot`, `product`).

---

## Por qué existe esta spec

Hoy la información de una tarea está repartida en ocho endpoints: la tarea, sus empleados, tiempos muertos, bitácora, observaciones y las tomas de las tres familias. Los totales de las tomas (fila TOTALES del Excel) los calcula el frontend y nunca cruzan tareas. Responder "¿qué % de recuperación tuvo Recorte LD en la semana 45?" exige juntar todo a mano.

Esta spec agrega un agente conversacional que arma esas respuestas con tools. Las tools no dejan que el LLM sume ni calcule porcentajes: los resúmenes se calculan en PHP con las mismas fórmulas de `PalletCalculator`, `LotCalculator` y `ProductCalculator`.

Flujo del usuario:

1. El frontend (Vercel AI SDK, `useChat`) manda `POST /weekly-plan-task-assistant/ask` con `message` y, si continúa un chat, `conversation_id`.
2. El backend valida, verifica que la conversación sea del usuario y abre el stream.
3. El agente llama las tools que necesita (buscar tareas, detalle, tomas, resumen).
4. La respuesta llega en streaming con el protocolo de datos de Vercel. El `conversation_id` llega en el header `X-Conversation-Id`.
5. El frontend lista, rehidrata o borra conversaciones con los endpoints de conversación.

---

## Alcance

**Dentro:**

- Instalar `laravel/ai`, publicar `config/ai.php` y correr sus migraciones (`agent_conversations`, `agent_conversation_messages`).
- `HasConversations` en `App\Models\User`.
- Proveedor, modelo, rate limit y rango máximo de fechas configurables por `.env` en `config('services.weekly_plan_task_assistant')`.
- Agente `App\Ai\Agents\WeeklyPlanTaskAssistant` con `RemembersConversations` e instrucciones en español.
- 13 tools de solo lectura en `app/Ai/Tools/`:
  - Tareas: búsqueda con filtros, detalle, campos de captura, empleados, tiempos muertos, bitácora y observaciones.
  - Tomas: listado y resumen por cada familia (`pallet`, `lot`, `product`).
- Resúmenes de tomas por tarea o por filtro (plan semanal, semana/año, rango de fechas, línea, SKU), con desglose por tarea.
- `App\Calculators\CaptureSummaryCalculator` para los totales y porcentajes globales.
- Feature `WeeklyPlanTaskAssistant`: Interface, Service, Provider, Request, Resources y Controller.
- Endpoints: preguntar (stream), listar conversaciones, ver mensajes y eliminar conversación. Solo conversaciones propias.
- Rate limit por usuario en el endpoint de preguntar.
- Documentación en `public/openapi.yaml` y guía `references/weekly-plan-task-assistant.md`.

**Fuera de alcance (para specs futuras):**

- Cualquier escritura desde el agente (crear, editar, iniciar o finalizar tareas, registrar tomas). No hay tools de escritura ni aprobaciones.
- Consultas fuera de `WeeklyPlanTask` y sus relaciones (borradores de plan, transacciones de material de empaque, inventario).
- Material de empaque de la tarea (`packingMaterialItems`).
- Tope de filas por tool y `#[MaxSteps]`. Decidido no limitar en esta spec.
- Admin viendo conversaciones ajenas.
- Salida estructurada (gráficas, tablas tipadas). La respuesta es texto markdown.
- Medir o limitar el consumo de tokens por usuario.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

Esta feature no crea tablas propias. Usa las del SDK:

- `agent_conversations`: una fila por conversación, polimórfica al participante (`User`).
- `agent_conversation_messages`: mensajes de usuario, asistente y tool calls de cada conversación.

`App\Models\User` agrega el trait `Laravel\Ai\Concerns\HasConversations` (relación `conversations()`).

### Configuración

`config/services.php`:

```php
'weekly_plan_task_assistant' => [
    'provider' => env('AI_ASSISTANT_PROVIDER', 'anthropic'),
    'model' => env('AI_ASSISTANT_MODEL'),
    'rate_limit' => (int) env('AI_ASSISTANT_RATE_LIMIT', 20),
    'max_range_days' => (int) env('AI_ASSISTANT_MAX_RANGE_DAYS', 31),
],
```

- `provider`: key de `config('ai.providers')` (`anthropic`, `openai`, `gemini`…). Las API keys van en las variables del SDK (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, …).
- `model`: `null` usa el modelo por defecto del proveedor en `config/ai.php`.
- `rate_limit`: preguntas por minuto por usuario.
- `max_range_days`: rango máximo entre `operation_date_from` y `operation_date_to` en las tools. Default 31 (un mes).

`.env.example` agrega `AI_ASSISTANT_PROVIDER`, `AI_ASSISTANT_MODEL`, `AI_ASSISTANT_RATE_LIMIT`, `AI_ASSISTANT_MAX_RANGE_DAYS` y `ANTHROPIC_API_KEY`.

### Agente `App\Ai\Agents\WeeklyPlanTaskAssistant`

```php
class WeeklyPlanTaskAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string; // view('ai.weekly-plan-task-assistant', [...])
    public function tools(): iterable;                  // las 13 tools, resueltas con app()
}
```

- Sin `#[Provider]` ni `#[Model]`: el Controller pasa `provider` y `model` desde la config al llamar `stream()`.
- Las instrucciones viven en `resources/views/ai/weekly-plan-task-assistant.blade.php` y reciben `today` (`now()->toDateString()`) y `maxRangeDays`.

Las instrucciones dicen, en español:

- Responder en español y en markdown, solo con datos devueltos por las tools. Si una tool no devuelve el dato, decirlo; nunca inventar.
- Es de solo lectura. Ante un pedido de modificar algo, responder que solo puede consultar.
- Glosario de status: `1` Pendiente Entrega Material de Empaque, `2` Lista para confirmación de asignaciones, `3` Lista para ejecución, `4` En Progreso, `5` Finalizada.
- Cada línea captura en una sola familia (`capture_type`). Usar el detalle de la tarea para saber la familia y llamar las tools de esa familia.
- Fórmulas y significado de cada familia:
  - `pallet`: neto = báscula − tara; boleta = cajas × presentación; diferencial = neto − boleta (positivo = sobra peso).
  - `lot`: % recuperación = recortadas / MP aplicada; % sobremadurez = sobremaduro / MP aplicada; saldo GRN = ingreso − MP aplicada.
  - `product`: % recuperación = recortadas / materia prima; % rechazo = rechazo / materia prima.
- Para totales o porcentajes de varias filas o tareas, usar siempre la tool de resumen; no sumar a mano.
- Libras en `lbs`, porcentajes en escala 0–100 con 2 decimales. Citar el id de tarea al mencionar una.
- Si la pregunta no acota tareas (sin tarea, plan, semana o fechas), pedir que acote. El rango de fechas máximo es `maxRangeDays` días.

### Tools (`app/Ai/Tools/`)

Todas implementan `Laravel\Ai\Contracts\Tool`, reciben `WeeklyPlanTaskAssistantServiceInterface` por constructor y usan el trait `App\Ai\Tools\Concerns\RespondsToAssistant`:

- `json(mixed $data): string` — `json_encode` con `JSON_UNESCAPED_UNICODE`. Los Resources se resuelven con `->resolve()`.
- `attempt(Closure $callback): string` — ejecuta la tool. Una `ApiException` (`NotFoundError`, `BadRequestError`) se devuelve como `Error: {mensaje}` para que el LLM la explique. Otras excepciones se propagan.

Las tools validan sus argumentos con `$request->validate()`; los mensajes van en español y regresan al LLM.

| Tool | Argumentos | Devuelve |
| ---- | ---------- | -------- |
| `SearchWeeklyPlanTasks` | filtros de tareas (ver abajo) + `status?` (1–5) | `WeeklyPlanTaskAssistantTaskResource[]` |
| `GetWeeklyPlanTaskDetail` | `task_id` | `WeeklyPlanTaskAssistantTaskResource` + conteos de empleados, tiempos muertos, observaciones, bitácora y tomas |
| `GetWeeklyPlanTaskCaptureFields` | `task_id` | `capture_type` de la línea y sus `LineFieldResource` |
| `GetWeeklyPlanTaskEmployees` | `task_id` | `WeeklyPlanTaskEmployeeResource[]` (sin los soft-deleted) |
| `GetWeeklyPlanTaskTimeouts` | `task_id` | `WeeklyPlanTaskTimeoutResource[]` + `timeout_hours` total |
| `GetWeeklyPlanTaskLogs` | `task_id` | `WeeklyPlanTaskLogResource[]` ordenados por `created_at` |
| `GetWeeklyPlanTaskObservations` | `task_id` | `WeeklyPlanTaskObservationResource[]` |
| `ListPalletRecords` | `task_id` | `WeeklyPlanTaskPerformanceRecordResource[]` |
| `SummarizePalletRecords` | alcance de resumen | `CaptureSummaryResource` (`pallet`) |
| `ListLotRecords` | `task_id` | `WeeklyPlanTaskLotRecordResource[]` |
| `SummarizeLotRecords` | alcance de resumen | `CaptureSummaryResource` (`lot`) |
| `ListProductRecords` | `task_id` | `WeeklyPlanTaskProductRecordResource[]` |
| `SummarizeProductRecords` | alcance de resumen | `CaptureSummaryResource` (`product`) |

**Filtros de tareas** (búsqueda y alcance de resumen por filtro):

```text
weekly_plan_id        integer   nullable
week                  integer   nullable|between:1,53   (requiere year)
year                  integer   nullable                (requiere week)
operation_date_from   date      nullable  Y-m-d         (requiere operation_date_to)
operation_date_to     date      nullable  Y-m-d         (≥ from, ≤ from + max_range_days)
line_code             string    nullable
sku_code              string    nullable  (LIKE, igual que getWeeklyPlanTasks)
```

- Se exige al menos uno de: `weekly_plan_id`, `week` + `year` u `operation_date_from` + `operation_date_to`. Si no → `BadRequestError('Acota la búsqueda con un plan semanal, una semana o un rango de fechas')`.
- Rango mayor a `max_range_days` → `BadRequestError('El rango máximo de fechas es de {N} días')`.
- `line_code` y `sku_code` solo refinan; no bastan solos.

**Alcance de resumen:** `task_id` **o** los filtros de tareas, nunca ambos ni ninguno → `BadRequestError('Indica una tarea o filtros, no ambos')`.

- Con `task_id`: si la línea de la tarea no es de la familia → `BadRequestError('La tarea {id} captura por {unitLabel}, no por {unitLabel de la tool}')`.
- Con filtros: solo entran tareas cuya línea es de la familia de la tool. Sin tareas → resumen con conteos en 0 y totales en `null`.

Las tools de listado por familia aplican la misma validación de familia con `task_id`.

### `App\Calculators\CaptureSummaryCalculator`

```php
/**
 * @param  Collection<int, WeeklyPlanTaskPerformanceRecord>  $records
 * @param  Collection<int, WeeklyPlanTask>  $tasks  tasks in scope, with performance.sku and performance.line
 */
public function pallet(Collection $records, Collection $tasks): array
public function lot(Collection $records, Collection $tasks): array
public function product(Collection $records, Collection $tasks): array
```

Reglas comunes:

- Suma de columna: ignora `null`. Si todas las filas son `null` → `null`.
- % global ponderado = `Σ numerador / Σ denominador × 100`, sumando solo las filas donde ambos no son `null`. `null` si el denominador suma 0.
- `planned_pounds` = Σ `boxes × presentation` de las tareas del alcance (igual que `WeeklyPlanTaskResource`).
- `progress_pct` = `produced_lbs / planned_pounds × 100`; `null` si `planned_pounds` es 0.
- Precisión completa; `CaptureSummaryResource` redondea a 2 decimales.

Forma de salida:

```php
[
    'capture_type' => 'pallet' | 'lot' | 'product',
    'tasks_count' => int,
    'records_count' => int,
    'planned_pounds' => ?float,
    'produced_lbs' => ?float,   // pallet: Σ net_weight; lot y product: Σ trimmed_lbs
    'progress_pct' => ?float,
    'totals' => [/* por familia, ver tabla */],
    'groups' => [/* por familia, ver tabla */],
    'by_task' => [ // solo cuando el alcance es por filtro
        ['task_id', 'line_code', 'sku_code', 'operation_date', 'status', 'records_count', 'produced_lbs', 'progress_pct', /* % globales de la familia */],
    ],
]
```

| Familia | `totals` (sumas) | % globales | `groups` |
| ------- | ---------------- | ---------- | -------- |
| `pallet` | `boxes`, `liters`, `scale_weight`, `tare`, `net_weight`, `ticket_weight`, `difference` | `difference_pct` = Σ`difference` / Σ`ticket_weight` | por `status` del registro: `status`, `records_count`, `net_weight` |
| `lot` | `intake_lbs`, `applied_raw_lbs`, `trimmed_lbs`, `overripe_lbs`, `grn_balance` | `recovery_pct` = Σ`trimmed_lbs` / Σ`applied_raw_lbs`; `overripe_pct` = Σ`overripe_lbs` / Σ`applied_raw_lbs` | por `lot`: `lot`, `records_count`, `applied_raw_lbs`, `trimmed_lbs`, `recovery_pct` |
| `product` | `raw_lbs`, `classified_lbs`, `trimmed_lbs`, `rejected_lbs`, `packed_lbs` | `recovery_pct` = Σ`trimmed_lbs` / Σ`raw_lbs`; `rejection_pct` = Σ`rejected_lbs` / Σ`raw_lbs` | por `raw_material`: `raw_material`, `records_count`, `raw_lbs`, `trimmed_lbs`, `rejected_lbs`, `recovery_pct`, `rejection_pct` |

Los `groups` con clave `null` se agrupan como `"(sin valor)"`.

### Feature `WeeklyPlanTaskAssistant`

`App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface`:

```php
// Consultas para las tools
public function searchTasks(array $filters): Collection;
public function getTaskDetail(string $taskId): WeeklyPlanTask;
public function getTaskCaptureFields(string $taskId): Collection;
public function getTaskEmployees(string $taskId): Collection;
public function getTaskTimeouts(string $taskId): Collection;
public function getTaskLogs(string $taskId): Collection;
public function getTaskObservations(string $taskId): Collection;
public function getCaptureRecords(string $taskId, CaptureType $captureType): Collection;
public function summarizeCaptureRecords(CaptureType $captureType, array $scope): array;

// Conversaciones
public function assertConversationBelongsTo(User $user, string $conversationId): void;
public function getConversations(User $user, ?string $limit);
public function getConversationMessages(User $user, string $conversationId);
public function deleteConversation(User $user, string $conversationId): bool;
```

`WeeklyPlanTaskAssistantService`:

- Depende de `WeeklyPlanTasksServiceInterface` (`getWeeklyPlanTaskById` y su `NotFoundError('La tarea del plan semanal no existe')`), de `CaptureSummaryCalculator` y de `Laravel\Ai\Contracts\ConversationStore`.
- `searchTasks` y `getTaskDetail` cargan lo mismo que `getWeeklyPlanTasks` (`performance.sku.client`, `performance.line.positions`, `openTimeout`, `weeklyPlan` y los cuatro `withSum`) para no tener N+1 al resolver el Resource.
- Las consultas de registros usan `with('user')` y ordenan por `created_at`.
- `assertConversationBelongsTo` usa `conversationBelongsTo($id, $user->getMorphClass(), $user->id)`. Si no → `NotFoundError('La conversación no existe')`.
- `deleteConversation` borra mensajes y conversación dentro de `DB::transaction`.
- Devuelve modelos, colecciones y arrays crudos; nunca JSON.

`App\Providers\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantProvider` hace el `bind` y se registra en `bootstrap/providers.php`.

### Resources (`app/Http/Resources/WeeklyPlanTaskAssistant/`)

- `WeeklyPlanTaskAssistantTaskResource` extiende `WeeklyPlanTaskResource` y agrega `capture_type`, `week` y `year` del plan.
- `WeeklyPlanTaskLogResource`: `id`, `event`, `field`, `old_value`, `new_value`, `user_name`, `created_at` (`d-m-Y H:i`).
- `CaptureSummaryResource`: la forma de salida del calculador con números redondeados a 2 decimales.
- `ConversationResource`: `id`, `title`, `created_at`, `updated_at` (`d-m-Y H:i`).
- `PaginatedConversationsResource`: `data`, `total`, `currentPage`, `lastPage`.
- `ConversationMessageResource`: `id`, `role`, `content`, `created_at`.

Los demás Resources se reusan de sus features: `LineFieldResource`, `WeeklyPlanTaskEmployeeResource`, `WeeklyPlanTaskTimeoutResource`, `WeeklyPlanTaskObservationResource` y los tres de registros de captura.

### Endpoints

En `routes/weeklyplantasks.php`, grupo `// FUNCTIONALITYS`, bajo `jwt.auth`. Controller `WeeklyPlanTaskAssistantController`.

| Método | Ruta | Body / query | Respuesta |
| ------ | ---- | ------------ | --------- |
| `POST` | `/weekly-plan-task-assistant/ask` | `message`, `conversation_id?` | 200, stream (protocolo de datos de Vercel) + header `X-Conversation-Id` |
| `GET` | `/weekly-plan-task-assistant/conversations` | `?limit=` | 200, `PaginatedConversationsResource` o `ConversationResource::collection` |
| `GET` | `/weekly-plan-task-assistant/conversations/{id}/messages` | — | 200, `ConversationMessageResource::collection` en orden cronológico |
| `DELETE` | `/weekly-plan-task-assistant/conversations/{id}` | — | 200, `data: null` |

`ask` lleva además `throttle:weekly-plan-task-assistant`.

Mensajes de éxito:

- Listar: `Conversaciones Obtenidas Correctamente`.
- Mensajes: `Mensajes Obtenidos Correctamente`.
- Eliminar: `Conversación Eliminada Correctamente`.

`ask` no usa `ResponseHandler::success`: devuelve el stream. Los errores **antes** de abrir el stream (422, 404 de conversación ajena, 429) sí usan el formato `{ statusCode, message, data }`.

Ejemplo:

```json
POST /weekly-plan-task-assistant/ask
{
  "message": "¿Cuál fue el % de recuperación de Recorte LD en la semana 45 de 2026?",
  "conversation_id": null
}
```

### `AskWeeklyPlanTaskAssistantRequest`

```text
message          string  required|max:2000
conversation_id  string  nullable|max:36
```

Mensajes en español: `El mensaje es obligatorio`, `El mensaje no puede superar 2000 caracteres`, `El id de la conversación no es válido`.

### Flujo de `ask` en el Controller

1. Valida el Request.
2. Con `conversation_id`: `assertConversationBelongsTo(auth()->user(), $id)`.
3. Arma el agente: `continue($id, as: $user)` si hay id; si no, `forUser($user)`.
4. `->stream($message, provider: config(...provider), model: config(...model))->usingVercelDataProtocol()`.
5. Agrega el header `X-Conversation-Id`.

### Rate limit

`AppServiceProvider::boot()` define `RateLimiter::for('weekly-plan-task-assistant')`:

- `Limit::perMinute(config('services.weekly_plan_task_assistant.rate_limit'))->by(auth()->id())`.
- Respuesta 429 con `{ statusCode: 429, message: 'Demasiadas preguntas, intenta de nuevo en un momento', data: null }`.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

> **Prerrequisito (lo hace el usuario):** `composer require laravel/ai`, publicar `config/ai.php` y las migraciones del SDK, y correr `php artisan migrate`. La implementación arranca con el SDK ya instalado.

1. **Configuración.** `HasConversations` en `User`. Bloque `weekly_plan_task_assistant` en `config/services.php` y variables en `.env.example`. Revisar en `vendor/laravel/ai`:
   - Las columnas de `agent_conversations` (confirmar `title`).
   - Si `stream()` acepta `provider` y `model`.
   - En qué momento `RemembersConversations` crea la conversación al hacer stream.

   Si la conversación no existe antes de emitir el primer byte, el Controller la crea primero con el `ConversationStore` y siempre usa `continue()`. Verificación: `php artisan config:show services.weekly_plan_task_assistant` muestra el bloque.
2. **`CaptureSummaryCalculator`.** Crear `app/Calculators/CaptureSummaryCalculator.php` con `pallet`, `lot` y `product`. Verificación en tinker con los ejemplos de las SPEC 09–11.
3. **Esqueleto de la feature.** Interface, Service vacío, Provider registrado en `bootstrap/providers.php`, Resources y Controller sin lógica.
4. **Consultas de tareas en el Service.** `searchTasks` con filtros y validación de alcance y rango, `getTaskDetail`, `getTaskCaptureFields`, `getTaskEmployees`, `getTaskTimeouts`, `getTaskLogs` y `getTaskObservations`.
5. **Consultas de tomas en el Service.** `getCaptureRecords` y `summarizeCaptureRecords` con validación de familia y alcance.
6. **Tools.** `php artisan make:tool` para las 13 tools y el trait `RespondsToAssistant`. Verificación en tinker llamando `handle()` con un `Request` armado a mano.
7. **Agente.** `php artisan make:agent WeeklyPlanTaskAssistant`, vista de instrucciones y `tools()`.
8. **Endpoint `ask`.** Request, rate limiter en `AppServiceProvider`, acción `ask`, header `X-Conversation-Id` y ruta. Verificación: `curl -N` con un JWT recibe el stream.
9. **Endpoints de conversación.** Listar, mensajes y eliminar, con verificación de pertenencia.
10. **Documentación.** Rutas, body, headers, schemas y errores en `public/openapi.yaml`.
11. **Guía de integración para frontend.** Crear `references/weekly-plan-task-assistant.md`:
    - Resumen de endpoints y configuración `.env`.
    - Ejemplo de `useChat` de `@ai-sdk/react` con el JWT y la lectura de `X-Conversation-Id`.
    - Rehidratar y borrar conversaciones.
    - Qué puede y qué no puede responder el agente, con ejemplos de preguntas por familia.
    - Errores (422, 404, 429 y errores dentro del stream) y checklist.
12. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `composer.json` incluye `laravel/ai` y `php artisan migrate` crea `agent_conversations` y `agent_conversation_messages`.
- [ ] `php artisan route:list --path=weekly-plan-task-assistant` muestra las 4 rutas bajo `jwt.auth`, y `ask` con `throttle:weekly-plan-task-assistant`.
- [ ] Cambiar `AI_ASSISTANT_PROVIDER` y `AI_ASSISTANT_MODEL` en `.env` cambia el proveedor y el modelo usados sin tocar código.
- [ ] `POST /weekly-plan-task-assistant/ask` sin JWT → 401; sin `message` → 422 `El mensaje es obligatorio`.
- [ ] `ask` responde con el protocolo de datos de Vercel y el header `X-Conversation-Id`.
- [ ] Una segunda pregunta con ese `conversation_id` recuerda el contexto de la primera.
- [ ] `ask` con el `conversation_id` de otro usuario → 404 `La conversación no existe`, sin llamar al proveedor.
- [ ] Superar `AI_ASSISTANT_RATE_LIMIT` preguntas en un minuto → 429 `Demasiadas preguntas, intenta de nuevo en un momento`.
- [ ] `GET /weekly-plan-task-assistant/conversations` devuelve solo las del usuario; con `?limit=` devuelve `data`, `total`, `currentPage`, `lastPage`.
- [ ] `GET …/conversations/{id}/messages` devuelve los mensajes en orden cronológico; conversación ajena o inexistente → 404.
- [ ] `DELETE …/conversations/{id}` borra la conversación y sus mensajes; conversación ajena → 404 y no borra nada.
- [ ] `SearchWeeklyPlanTasks` sin plan, semana ni rango → `Error: Acota la búsqueda con un plan semanal, una semana o un rango de fechas`.
- [ ] `SearchWeeklyPlanTasks` con un rango de 32 días y `AI_ASSISTANT_MAX_RANGE_DAYS=31` → `Error: El rango máximo de fechas es de 31 días`.
- [ ] `SearchWeeklyPlanTasks` con `week` + `year` devuelve las tareas de ese plan con `capture_type`, `week` y `year`, sin N+1.
- [ ] `GetWeeklyPlanTaskDetail` con id inexistente → `Error: La tarea del plan semanal no existe`.
- [ ] `ListLotRecords` o `SummarizeLotRecords` sobre una tarea `pallet` → `Error: La tarea {id} captura por tarima, no por lote`. Igual para las otras combinaciones.
- [ ] `SummarizePalletRecords` de una tarea con registros `net_weight` 2354 y 2340, `ticket_weight` 2340 y 2340 devuelve `net_weight = 4694`, `difference = 14` y `difference_pct = 0.3`.
- [ ] `SummarizeLotRecords` con dos lotes (`applied_raw_lbs` 18064.5 y 10000, `trimmed_lbs` 8127 y 5000) devuelve `recovery_pct = 46.77` (ponderado, no el promedio de 44.99 y 50).
- [ ] `SummarizeProductRecords` con `raw_lbs` 1000 y `trimmed_lbs` 800 en dos filas del mismo `raw_material` devuelve un solo grupo con `raw_lbs = 2000` y `recovery_pct = 80`.
- [ ] Un resumen por filtro (semana + `line_code`) solo incluye tareas de la familia de la tool y devuelve `by_task` con una fila por tarea.
- [ ] Un resumen con `task_id` y filtros a la vez → `Error: Indica una tarea o filtros, no ambos`.
- [ ] Con denominador 0 o todo en `null`, los porcentajes del resumen son `null` (sin división por cero).
- [ ] Pedirle al agente "finaliza la tarea 210" no cambia nada en BD y responde que solo puede consultar.
- [ ] Ninguna tool ejecuta `insert`, `update` ni `delete` (revisión del código de `app/Ai/Tools` y de las consultas del Service).
- [ ] `public/openapi.yaml` documenta las 4 rutas, el header `X-Conversation-Id` y los errores.
- [ ] `references/weekly-plan-task-assistant.md` existe y cubre endpoints, `.env`, `useChat`, conversaciones y errores.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** Laravel AI SDK (`laravel/ai`). Pedido explícito; es el SDK oficial del framework. Agrega una dependencia con aprobación del usuario.
- **Sí:** proveedor y modelo por `.env`, sin `#[Provider]` ni `#[Model]`. Permite cambiar de proveedor sin desplegar código.
- **Sí:** config en `config('services.weekly_plan_task_assistant')`. Mismo lugar que `services.biometric`; `config/ai.php` queda con lo que publica el SDK.
- **Sí:** consultas sobre cualquier tarea con filtros. Las preguntas útiles cruzan tareas ("la semana 45", "la línea X").
- **Sí:** exigir plan, semana o rango de fechas para buscar o resumir. Sin tope de filas, es lo único que acota el volumen.
- **Sí:** rango máximo configurable, 31 días por defecto. Pedido del usuario: configurable, un mes por defecto.
- **Sí:** rate limit configurable, 20 por minuto por defecto. El usuario lo pidió configurable sin un valor fijo; 20 es un default razonable para chat.
- **No:** `#[MaxSteps]` ni tope de filas por tool. Decisión del usuario; el riesgo queda en la tabla de riesgos.
- **Sí:** historial con `RemembersConversations`. El usuario hace preguntas de seguimiento ("¿y la tarea anterior?").
- **Sí:** solo conversaciones propias, también para admin. `continue()` del SDK no verifica dueño; lo verifica el Service.
- **Sí:** streaming con el protocolo de datos de Vercel. El frontend usa `useChat` de `@ai-sdk/react`.
- **Sí:** `conversation_id` en el header `X-Conversation-Id`. El stream no pasa por `ResponseHandler`; el header no depende del formato del protocolo.
- **Sí:** cualquier usuario `jwt.auth`. Mismo acceso que los endpoints de tareas que el agente consulta.
- **Sí:** listado y resumen separados por familia (6 tools). Cada tool documenta sus columnas y fórmulas; el LLM no tiene que adivinar qué columnas aplican.
- **No:** una tool genérica de tomas. Su schema mezclaría las columnas de tres familias.
- **No:** dejar que el LLM sume o calcule porcentajes. Los LLM se equivocan en aritmética; el resumen se calcula en PHP.
- **Sí:** porcentajes globales ponderados (`Σnum / Σden`). Es lo que hace la fila TOTALES del Excel; el promedio de porcentajes da otro número.
- **Sí:** `CaptureSummaryCalculator` en `app/Calculators`. Mismo lugar que los calculadores de cada familia.
- **Sí:** feature `WeeklyPlanTaskAssistant` con Service de solo lectura. Respeta la arquitectura por capas; las tools quedan delgadas.
- **No:** reusar los Services de cada familia para listar. Sus listados exigen `weeklyPlanTaskId` por query y no validan familia.
- **Sí:** reusar `getWeeklyPlanTaskById` y los Resources existentes. Mismos 404 y mismo formato que el resto de la API.
- **Sí:** errores de negocio devueltos a la tool como texto `Error: …`. El LLM los explica al usuario en vez de cortar el stream.
- **Sí:** instrucciones en una vista Blade. Se editan sin tocar PHP y reciben la fecha y el rango máximo.
- **Sí:** rutas en `routes/weeklyplantasks.php`. Es parte de la feature de tareas.
- **No:** material de empaque de la tarea. El usuario lo dejó fuera.
- **No:** tools de escritura. El agente es de consulta; escribir requiere aprobaciones y otra spec.
- **No:** tests automatizados. Consistente con SPEC 01–11; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Sin `#[MaxSteps]` ni tope de filas, un resumen de un mes de una línea `pallet` o un loop de tools consume muchos tokens. | Rango máximo de fechas y rate limit configurables. Las instrucciones piden usar el resumen en vez del listado para totales. Si el costo sube, se agrega el tope en otra spec. |
| El SDK no expone el `conversation_id` antes de abrir el stream. | El paso 1 lo verifica. Si no lo expone, el Controller crea la conversación con el `ConversationStore` antes del stream y siempre usa `continue()`. |
| `stream()` no acepta `provider` / `model` como argumentos. | El paso 1 lo verifica. Alternativa: método `provider()` del agente que arma el proveedor con `Ai::build()` desde la config. |
| Un error del proveedor ocurre con el stream ya abierto (status 200 enviado). | El protocolo de Vercel emite una parte de error; la guía de frontend explica cómo mostrarla. |
| El LLM inventa datos o responde con números que no vienen de las tools. | Instrucciones explícitas de solo usar tools; los totales vienen calculados. El frontend muestra que la respuesta es generada por IA. |
| Datos de producción (nombres de empleados, pesos) salen hacia el proveedor externo. | Se acepta; el proveedor se elige por `.env`. Un proveedor local (`ollama`, `openai-compatible`) evita la salida de datos. |
| `continue()` del SDK no verifica dueño. | `assertConversationBelongsTo` se llama siempre antes de `continue()` y en mensajes y eliminar. |
| Las tablas del SDK cambian en una versión futura de `laravel/ai`. | Se fija la versión en `composer.json`; `deleteConversation` usa el `ConversationStore` o las tablas que crea el SDK. |

---

## Lo que **no** está en esta spec

- Tools de escritura o acciones sobre tareas.
- Consultas fuera de `WeeklyPlanTask` y sus relaciones.
- Material de empaque de la tarea.
- `#[MaxSteps]` y tope de filas por tool.
- Admin viendo conversaciones ajenas.
- Salida estructurada o gráficas.
- Límite de tokens por usuario.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
