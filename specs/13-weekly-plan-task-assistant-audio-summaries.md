# SPEC 13 — Resúmenes en audio del asistente IA de tareas

> **Estado:** Aprobado
> **Depende de:** SPEC 05, SPEC 12
> **Fecha:** 2026-11-10
> **Objetivo:** Agregar al agente `WeeklyPlanTaskAssistant` una tool que convierte a voz, con Gemini, un guion de resumen escrito por el LLM, lo guarda como archivo de audio en S3 y devuelve su URL pública para escucharlo.

---

## Por qué existe esta spec

El asistente de SPEC 12 responde en markdown. Quien está en planta no siempre puede leer tablas en pantalla. Esta spec le da al agente una tool para generar un audio del resumen.

El LLM no lee su respuesta en markdown tal cual. Escribe un **guion** pensado para oírse: sin tablas ni símbolos y con los números como se dicen. La tool convierte ese guion a voz con `Laravel\Ai\Audio`.

Gemini devuelve PCM, y el SDK lo envuelve en WAV (`audio/wav`, 24 kHz, 16 bits, mono). No es mp3, pero el usuario aceptó cualquier formato de audio. La extensión del archivo sale del MIME de la respuesta (`.wav` con Gemini).

Flujo del usuario:

1. El usuario pregunta en el chat de SPEC 12: "¿Cómo fue la recuperación de Recorte LD en la semana 45? Dámelo en audio".
2. El agente consulta los datos con las tools de SPEC 12.
3. El agente escribe el guion y llama `GenerateAudioSummary`.
4. La tool genera el audio, lo sube a S3, guarda el registro y devuelve `audio_url`.
5. El frontend ve la parte `tool-output-available` de `GenerateAudioSummary` y pinta un reproductor. La respuesta de texto del agente también trae el link `[Escuchar resumen](url)`.

---

## Alcance

**Dentro:**

- Tool `App\Ai\Tools\GenerateAudioSummary` (la 14.ª del agente).
- Tabla `assistant_audio_summaries` y modelo `App\Models\AssistantAudioSummary`.
- Generación síncrona con `Audio::of($script)->voice(...)->timeout(...)->generate(provider, model)`.
- Archivo en S3 con URL pública, igual que las firmas de SPEC 05.
- Proveedor, modelo, voz, timeout y largo máximo del guion configurables por `.env` en `config('services.weekly_plan_task_assistant.audio')`. Proveedor por defecto `gemini`.
- Instrucciones del agente: cuándo generar audio y cómo escribir el guion.
- `deleteConversation` de SPEC 12 borra también los audios de la conversación (filas y archivos en S3).
- Actualizar la guía `references/weekly-plan-task-assistant.md` con la sección de audio.

**Fuera de alcance (para specs futuras):**

- Endpoints REST nuevos para listar, descargar o borrar audios.
- Conversión a mp3 u otra compresión (ffmpeg).
- Partir guiones largos en trozos y concatenar audio.
- Generación en cola (`queue()`) y polling de estado.
- Elegir voz desde el chat (masculina o femenina). La voz es fija por `.env`.
- Exponer los audios en `ConversationMessageResource` al rehidratar una conversación.
- Rate limit propio del audio o límite diario de audios por usuario.
- Transcripción (voz a texto) de preguntas.
- Retención o borrado automático de audios viejos.

---

## Modelo de datos

### Tabla `assistant_audio_summaries`

```php
Schema::create('assistant_audio_summaries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->string('conversation_id', 36);
    $table->foreign('conversation_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
    $table->string('path');              // key en S3
    $table->string('mime_type', 50);     // audio/wav con Gemini
    $table->unsignedInteger('characters'); // largo del guion
    $table->string('provider', 50);
    $table->string('model', 100)->nullable(); // null = modelo por defecto del SDK
    $table->string('voice', 50);
    $table->timestamps();

    $table->index(['conversation_id', 'created_at']);
});
```

No se guarda el guion. El texto ya queda en `tool_calls` de `agent_conversation_messages`.

### Modelo `App\Models\AssistantAudioSummary`

- `$fillable`: `user_id`, `conversation_id`, `path`, `mime_type`, `characters`, `provider`, `model`, `voice`.
- Relación `user()` (`belongsTo`).

### Archivo en S3

- Disco: `Storage::disk('s3')`, igual que SPEC 05.
- Key: `weekly-plan-task-assistant/audios/{conversation_id}/{nombre aleatorio}.{ext}`. El nombre y la extensión salen de `AudioResponse::store()` (`.wav` para `audio/wav`, `.mp3` en otro caso).
- URL: `Storage::disk('s3')->url($path)`, pública y sin caducidad.

### Configuración

`config/services.php`, dentro de `weekly_plan_task_assistant`:

```php
'audio' => [
    'provider' => env('AI_ASSISTANT_AUDIO_PROVIDER', 'gemini'),
    'model' => env('AI_ASSISTANT_AUDIO_MODEL'),
    'voice' => env('AI_ASSISTANT_AUDIO_VOICE', 'Kore'),
    'timeout' => (int) env('AI_ASSISTANT_AUDIO_TIMEOUT', 120),
    'max_chars' => (int) env('AI_ASSISTANT_AUDIO_MAX_CHARS', 4000),
],
```

- `provider`: key de `config('ai.providers')` con soporte de audio (`gemini`, `openai`, `eleven`, `mistral`).
- `model`: `null` usa el modelo TTS por defecto del proveedor (`gemini-3.1-flash-tts-preview` en Gemini).
- `voice`: nombre de voz del proveedor. `Kore` es la voz femenina por defecto de Gemini.
- `timeout`: segundos de espera al proveedor TTS.
- `max_chars`: largo máximo del guion.

`.env.example` agrega `GEMINI_API_KEY`, `AI_ASSISTANT_AUDIO_PROVIDER`, `AI_ASSISTANT_AUDIO_MODEL`, `AI_ASSISTANT_AUDIO_VOICE`, `AI_ASSISTANT_AUDIO_TIMEOUT` y `AI_ASSISTANT_AUDIO_MAX_CHARS`.

### Tool `App\Ai\Tools\GenerateAudioSummary`

- Implementa `Laravel\Ai\Contracts\Tool` y usa `RespondsToAssistant`, igual que las tools de SPEC 12.
- Constructor: `WeeklyPlanTaskAssistantServiceInterface $service`, `string $conversationId`, `User $user`.
- El agente la arma en `tools()` con `app(GenerateAudioSummary::class, ['conversationId' => $this->currentConversation(), 'user' => $this->conversationParticipant()])`. El Controller de SPEC 12 siempre crea la conversación antes del stream, así que `currentConversation()` nunca es `null`.

Argumentos:

```text
script   string   required|max:{audio.max_chars}
```

Mensajes de validación: `El guion del audio es obligatorio`, `El guion no puede superar {N} caracteres`.

Devuelve (JSON con `json()`):

```json
{
  "audio_id": 12,
  "audio_url": "https://<bucket>.s3.amazonaws.com/weekly-plan-task-assistant/audios/<conversation_id>/<nombre>.wav",
  "mime_type": "audio/wav",
  "characters": 812
}
```

### Service

`WeeklyPlanTaskAssistantServiceInterface` agrega:

```php
public function generateAudioSummary(User $user, string $conversationId, string $script): AssistantAudioSummary;
```

`WeeklyPlanTaskAssistantService::generateAudioSummary`:

1. `set_time_limit(audio.timeout + 60)` para que PHP no corte el stream mientras espera al TTS.
2. `Audio::of($script)->voice(audio.voice)->timeout(audio.timeout)->generate(audio.provider, audio.model ?: null)`.
3. Si el proveedor falla (cualquier excepción del SDK) → `report($th)` y `BadRequestError('No se pudo generar el audio, intenta de nuevo')`.
4. `$audio->store("weekly-plan-task-assistant/audios/{$conversationId}", 's3')`.
5. Crea la fila `AssistantAudioSummary`. Si el insert falla, borra el archivo de S3 y relanza.
6. Devuelve el modelo.

`deleteConversation` (SPEC 12) cambia así:

1. Antes de la transacción, toma los `path` de `assistant_audio_summaries` de esa conversación.
2. Dentro de `DB::transaction`, borra también las filas de `assistant_audio_summaries`, además de mensajes y conversación.
3. Después del commit, `Storage::disk('s3')->delete($paths)`. Si falla el borrado en S3 → `report()`, sin error al usuario.

### Resource

`App\Http\Resources\WeeklyPlanTaskAssistant\AssistantAudioSummaryResource`: `audio_id` (`id`), `audio_url` (`Storage::disk('s3')->url($this->path)`), `mime_type`, `characters`. La tool lo resuelve con `->resolve()`.

### Instrucciones del agente

`resources/views/ai/weekly-plan-task-assistant.blade.php` recibe además `maxAudioChars` y agrega, en español:

- Generar audio solo cuando el usuario lo pide ("en audio", "quiero escucharlo", "léemelo", "mp3").
- Primero obtener los datos con las tools de consulta; el guion solo usa datos devueltos por tools.
- El guion va en español y en texto plano: sin markdown, tablas, listas, emojis ni URLs.
- Números como se dicen: "cuatro mil seiscientas noventa y cuatro libras", "cuarenta y seis punto setenta y siete por ciento". Unidades completas: "libras", no "lbs".
- Máximo `maxAudioChars` caracteres. Si no cabe, resumir más; nunca partir en varios audios.
- Llamar `GenerateAudioSummary` una sola vez por pedido.
- Después de la tool, responder con un texto corto y el link en markdown `[Escuchar resumen]({audio_url})`.
- Si la tool devuelve `Error: …`, explicarlo y no inventar un link.
- Generar el audio no modifica datos de producción; sigue siendo de solo lectura sobre las tareas.

### Endpoints

Esta spec no agrega endpoints. El audio sale por el stream de `POST /weekly-plan-task-assistant/ask` (SPEC 12):

- Parte `tool-input-available` con `toolName` de `GenerateAudioSummary` y el `script`.
- Parte `tool-output-available` con el JSON de la tool como string. El frontend hace `JSON.parse` y lee `audio_url`.
- `text-delta` con el link `[Escuchar resumen](url)`. Al rehidratar la conversación, este link en `content` es lo que queda visible.

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Configuración.** Bloque `audio` en `config/services.php` y variables en `.env.example`. Revisar en `vendor/laravel/ai` el nombre de tool que emite el protocolo de Vercel (`toolName`) y la forma exacta de `tool-output-available`. Verificación: `php artisan config:show services.weekly_plan_task_assistant` muestra `audio`.
2. **Migración y modelo.** `php artisan make:model AssistantAudioSummary -m`. Columnas, FK, índice, `$fillable` y `user()`. Verificación: `php artisan migrate` crea la tabla.
3. **Service.** `generateAudioSummary` en Interface y Service, y `AssistantAudioSummaryResource`. Verificación en tinker: genera un audio corto con Gemini, el archivo existe en S3 y la URL se reproduce en el navegador.
4. **Borrado.** `deleteConversation` borra filas y archivos de audio. Verificación: borrar una conversación con un audio elimina la fila y el archivo.
5. **Tool.** `php artisan make:tool GenerateAudioSummary`, validación de `script` y respuesta con el Resource. Verificación en tinker llamando `handle()` con un `Request` armado a mano.
6. **Agente.** Registrar la tool en `tools()` con `conversationId` y `user`. Pasar `maxAudioChars` a la vista y agregar las reglas de audio a las instrucciones. Verificación: `curl -N` a `ask` con "dame en audio el resumen de la tarea {id}" emite `tool-output-available` con `audio_url`.
7. **Guía de frontend.** Sección "Resúmenes en audio" en `references/weekly-plan-task-assistant.md`:
   - Cómo pedirlo y qué devuelve la tool.
   - Ejemplo con `useChat`: detectar la parte de `GenerateAudioSummary` en `message.parts`, `JSON.parse` del output y `<audio controls src={audio_url}>`.
   - Estado de carga mientras la tool corre (puede tardar decenas de segundos).
   - Formato `.wav` y tamaño aproximado (~2.9 MB por minuto).
   - Errores (`Error: …` dentro del stream) y variables `.env`.
8. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `assistant_audio_summaries` con FK a `users` y a `agent_conversations`.
- [ ] `php artisan config:show services.weekly_plan_task_assistant` muestra el bloque `audio` con `provider = gemini`, `voice = Kore`, `timeout = 120` y `max_chars = 4000` por defecto.
- [ ] Pedirle al agente "dame en audio el resumen de lotes de la tarea {id}" emite una parte `tool-output-available` de `GenerateAudioSummary` con `audio_url`, `mime_type = audio/wav` y `characters`.
- [ ] La `audio_url` abre y se reproduce en el navegador sin autenticación.
- [ ] El archivo queda en S3 bajo `weekly-plan-task-assistant/audios/{conversation_id}/` con extensión `.wav`.
- [ ] Se crea una fila en `assistant_audio_summaries` con el `user_id`, `conversation_id`, `path`, `provider = gemini` y `voice` de la config.
- [ ] La respuesta de texto del agente incluye el link `[Escuchar resumen](url)` con la misma URL de la tool.
- [ ] El guion de la tool call no contiene `|`, `#`, `*`, `lbs` ni `%` (revisión de `tool_calls` en `agent_conversation_messages` de 3 pedidos de prueba).
- [ ] Una pregunta sin pedir audio no llama `GenerateAudioSummary`.
- [ ] `GenerateAudioSummary` con un `script` de 4001 caracteres y `AI_ASSISTANT_AUDIO_MAX_CHARS=4000` → `Error: El guion no puede superar 4000 caracteres`, sin llamar al proveedor TTS.
- [ ] Con `GEMINI_API_KEY` inválida, la tool devuelve `Error: No se pudo generar el audio, intenta de nuevo`, el stream termina normal y no se crea fila ni archivo.
- [ ] Cambiar `AI_ASSISTANT_AUDIO_PROVIDER=openai` y `AI_ASSISTANT_AUDIO_VOICE=alloy` genera el audio con OpenAI (`audio/mpeg`, `.mp3`) sin tocar código.
- [ ] Un audio de ~4000 caracteres se genera sin que PHP corte el stream por `max_execution_time`.
- [ ] `DELETE /weekly-plan-task-assistant/conversations/{id}` borra las filas de `assistant_audio_summaries` de esa conversación y sus archivos en S3.
- [ ] `DELETE` de una conversación ajena → 404 y no borra audios.
- [ ] `GenerateAudioSummary` no escribe en tablas de producción (`weekly_plan_tasks` ni registros de captura); solo inserta en `assistant_audio_summaries` (revisión de código).
- [ ] `references/weekly-plan-task-assistant.md` tiene la sección "Resúmenes en audio" con ejemplo de `useChat` y `<audio>`.
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** tool del agente en vez de endpoint aparte. El pedido es que "el agente" genere el audio, y el agente decide cuándo hacerlo según la conversación.
- **No:** endpoint "escuchar" sobre un mensaje existente. Dos caminos para lo mismo; se puede agregar en otra spec.
- **Sí:** guion escrito por el LLM. Tablas y markdown leídos en voz suenan mal; el LLM ya tiene los datos y sabe resumirlos para oído.
- **No:** limpiar el markdown de la respuesta en PHP. Quita símbolos pero no vuelve legible una tabla.
- **Sí:** Gemini como proveedor por defecto. Decisión del usuario; el proveedor sigue configurable por `.env`.
- **Sí:** WAV tal cual. Gemini no devuelve mp3 y el usuario aceptó cualquier formato de audio. Evita instalar ffmpeg.
- **No:** convertir a mp3 con ffmpeg. Dependencia de sistema y proceso externo. Si el tamaño molesta, va en otra spec.
- **Sí:** guion máximo de 4000 caracteres configurable. Acota tamaño (~12–15 MB en WAV), tiempo de generación y costo.
- **No:** partir en trozos y concatenar. Complejidad alta para un resumen que debe ser corto.
- **Sí:** generación síncrona dentro de la tool. El stream ya está abierto y el frontend ve la tool corriendo; no hace falta polling.
- **No:** `queue()` del SDK. Requiere un endpoint de estado y lógica en el frontend.
- **Sí:** S3 con URL pública. Decisión del usuario; mismo patrón que las firmas de SPEC 05 y no caduca en el historial.
- **No:** URL temporal firmada. Caducaría en el historial y obligaría a re-firmar.
- **Sí:** tabla propia `assistant_audio_summaries`. Permite borrar los archivos al eliminar la conversación y auditar qué se generó.
- **No:** guardar el guion en la tabla. Ya está en `tool_calls` del mensaje.
- **Sí:** voz, modelo y timeout por `.env`. Se cambian sin desplegar código.
- **No:** voz elegida desde el chat. Más argumentos y nombres de voz distintos por proveedor.
- **Sí:** borrar filas y archivos al eliminar la conversación. Evita archivos huérfanos con datos de producción.
- **Sí:** FK `conversation_id` con `cascadeOnDelete`. Respaldo si algo borra la conversación por fuera del Service.
- **Sí:** archivos borrados de S3 después del commit. Si la transacción falla, los archivos siguen siendo válidos.
- **No:** endpoints REST para audios. El audio llega en el stream y el link queda en el historial.
- **No:** audios en `ConversationMessageResource`. El link en `content` basta para rehidratar.
- **Sí:** errores del proveedor TTS como `BadRequestError`. La tool los devuelve como `Error: …` y el LLM los explica sin cortar el stream.
- **Sí:** esta tool escribe (S3 y su tabla), a diferencia de SPEC 12. Solo escribe sus propios artefactos; no toca datos de producción.
- **No:** rate limit propio del audio. El rate limit de `ask` ya acota los pedidos.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| El TTS de un guion largo tarda más que `max_execution_time` de PHP o del proxy y corta el stream. | `set_time_limit(timeout + 60)` en el Service. La guía documenta revisar el timeout de Nginx o del balanceador. `max_chars` acota la duración. |
| WAV pesado (~12–15 MB por audio largo) en S3 y en el navegador móvil. | `max_chars` configurable. La compresión a mp3 queda para otra spec. |
| La URL pública expone datos de producción a quien la tenga. | Nombre aleatorio de 40 caracteres. Borrado al eliminar la conversación. Decisión aceptada por el usuario. |
| El modelo TTS de Gemini es `preview` y puede cambiar o desaparecer. | `AI_ASSISTANT_AUDIO_MODEL` y `AI_ASSISTANT_AUDIO_PROVIDER` permiten cambiar sin desplegar. |
| El LLM escribe un guion con markdown, números en cifras o datos inventados. | Instrucciones explícitas; el criterio de aceptación revisa los guiones de prueba. |
| El LLM llama la tool varias veces en un pedido y multiplica costo y archivos. | Instrucción de llamarla una vez. El rate limit de `ask` acota el total. |
| Falla el borrado en S3 después del commit y quedan archivos huérfanos. | `report()` del error. Las filas ya no existen; una limpieza por prefijo queda para otra spec. |
| Datos de producción salen hacia Google al generar el audio. | Se acepta, igual que en SPEC 12. El proveedor se cambia por `.env`. |

---

## Lo que **no** está en esta spec

- Endpoints REST para audios.
- Conversión a mp3 o compresión.
- Guiones largos en varios trozos.
- Generación en cola.
- Voz elegida desde el chat.
- Audios en el historial rehidratado más allá del link.
- Rate limit o cuota propia de audios.
- Transcripción de voz a texto.
- Retención automática de audios.

Cada uno, si llega, va en su propia spec.
