# SPEC 06 — Personal asignado a cada WeeklyPlanTask

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-10-01
> **Objetivo:** Registrar qué `WeeklyPlanEmployee` trabajan en cada `WeeklyPlanTask`, partiendo de los empleados del plan cuyas posiciones pertenecen a la línea de la tarea, con reemplazos, altas y bajas, y guardando el historial de cambios.

---

## Por qué existe esta spec

Hoy el plan semanal tiene su listado de personal (`weekly_plan_employees`), pero no se sabe quién trabajó en cada tarea. Una tarea de la línea `1REM1A` usa a los empleados que ocupan las posiciones de esa línea (`1REM1001` … `1REM1025`). En una tarea trabajan todos; en otra, solo algunos o con reemplazos.

Flujo del usuario:

1. Entra a la pantalla de asignación de personal de la tarea.
2. Ve los **candidatos**: los `WeeklyPlanEmployee` del plan cuya posición pertenece a la línea de la tarea.
3. Confirma. Puede hacerlo sin cambios o con excepciones:
   - **Reemplazar** a un candidato por otro empleado del plan, en la misma posición.
   - **Agregar** a un empleado del plan sin reemplazar a nadie.
   - **Quitar** a un candidato sin reemplazo.
4. El backend crea todas las asignaciones con esas excepciones aplicadas y la tarea pasa a "Lista para ejecución".
5. Mientras la tarea está lista para ejecución o en progreso, se puede reemplazar, agregar o quitar personal uno por uno. Cada cambio deja historial.

La posición de un `WeeklyPlanEmployee` puede cambiar en el plan. Por eso cada asignación guarda una copia de la posición que ocupó en esa tarea: el histórico no cambia si después se edita el plan.

---

## Alcance

**Dentro:**

- Tabla `weekly_plan_task_employees`, modelo `WeeklyPlanTaskEmployee` y relaciones.
- Feature `WeeklyPlanTaskEmployees` con su Interface, Service, Provider, Requests, Resource y Controller.
- `GET /weekly-plan-tasks/{id}/availableEmployees`: candidatos de la tarea.
- `GET /weekly-plan-tasks/{id}/employees`: personal activo asignado a la tarea.
- `POST /weekly-plan-tasks/{id}/confirmEmployees`: confirmación con reemplazos, altas y bajas. Pasa la tarea de status `2` a `3`.
- `POST /weekly-plan-tasks/{id}/addEmployee`: alta individual después de confirmar.
- `PATCH /weekly-plan-task-employees/{id}/replace`: reemplazo individual después de confirmar.
- `DELETE /weekly-plan-task-employees/{id}`: baja individual después de confirmar. Soft delete: el registro queda en BD.
- Historial por filas con `SoftDeletes` (`deleted_at`) y `replaced_weekly_plan_employee_id`.
- Validación en `WeeklyPlanEmployeesService::deleteWeeklyPlanEmployeeById` para no borrar un empleado del plan que tiene asignaciones en tareas.

**Fuera de alcance (para specs futuras):**

- Validación de asistencia con el biométrico externo. El usuario decide quién faltó.
- Endpoint para consultar el historial (filas con soft delete). Se guarda, pero solo se listan las activas.
- Paginación de los listados de esta feature. Una tarea tiene decenas de empleados, no miles.
- Agregar empleados que **no** están en `weekly_plan_employees` del plan, o crear su `WeeklyPlanEmployee` automáticamente.
- Elegir una posición distinta para un empleado agregado.
- Validar que un empleado no esté en dos tareas a la vez (mismo día u horario).
- Horas trabajadas, destajo o pago por empleado y tarea.
- Copiar el personal al dividir una tarea (`splitTask`). Ya está bloqueado para status mayor que `2`.
- Registrar los cambios de personal en `weekly_plan_task_logs` o enviarlos por correo.
- Incluir el personal en `WeeklyPlanTaskResource`.
- Tests automatizados. El repositorio sigue sin carpeta `tests/`.

---

## Modelo de datos

### Migración: `create_weekly_plan_task_employees_table`

```php
Schema::create('weekly_plan_task_employees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('weekly_plan_employee_id')->constrained();
    $table->foreignId('position_id')->constrained();
    $table->foreignId('replaced_weekly_plan_employee_id')->nullable()->constrained('weekly_plan_employees');
    $table->softDeletes();
    $table->timestamps();

    $table->index(['weekly_plan_task_id', 'deleted_at']);
});
```

| Columna                            | Significado                                                                                   |
| ---------------------------------- | --------------------------------------------------------------------------------------------- |
| `weekly_plan_employee_id`          | Quién trabaja en la tarea. Siempre es un empleado del mismo plan que la tarea.                 |
| `position_id`                      | Copia de la posición que ocupa **en esta tarea**. No cambia si se edita el `WeeklyPlanEmployee`. |
| `replaced_weekly_plan_employee_id` | A quién reemplazó. `null` si es candidato original o alta sin reemplazo.                     |
| `deleted_at`                       | Soft delete. `null` = asignación activa. Con fecha = quitado o reemplazado en ese momento.  |

No hay índice único `(weekly_plan_task_id, weekly_plan_employee_id)`: un empleado quitado puede volver a agregarse y tener dos filas, una con soft delete y una activa. La unicidad **entre filas activas** se valida en el Service.

### Modelo `App\Models\WeeklyPlanTaskEmployee`

- Trait: `Illuminate\Database\Eloquent\SoftDeletes`.
- Fillable: `weekly_plan_task_id`, `weekly_plan_employee_id`, `position_id`, `replaced_weekly_plan_employee_id`.
- Relaciones: `task()` → `WeeklyPlanTask`, `weeklyPlanEmployee()` → `WeeklyPlanEmployee`, `position()` → `Position`, `replacedWeeklyPlanEmployee()` → `WeeklyPlanEmployee`.

Relaciones nuevas en modelos existentes:

- `WeeklyPlanTask::employees()` → `hasMany(WeeklyPlanTaskEmployee::class)`.
- `WeeklyPlanEmployee::taskAssignments()` → `hasMany(WeeklyPlanTaskEmployee::class)`.

### Reglas de posición

| Caso                           | `position_id` guardado                         | `replaced_weekly_plan_employee_id` |
| ------------------------------ | ---------------------------------------------- | ---------------------------------- |
| Candidato confirmado sin cambio | `position_id` actual de su `WeeklyPlanEmployee` | `null`                             |
| Reemplazo (al confirmar o después) | `position_id` del reemplazado en esta tarea | id del reemplazado                 |
| Alta sin reemplazo             | `position_id` actual de su `WeeklyPlanEmployee` | `null`                             |

### Candidatos

`WeeklyPlanEmployee` donde:

- `weekly_plan_id` = `weekly_plan_id` de la tarea.
- `position.line_id` = `performance.line_id` de la tarea (`line_stock_keeping_units.line_id`).
- `position.status` = `1`.

Se ordenan por `positions.code`.

### Status de la tarea

| Status | Mensaje actual                             | Confirmar | Alta / reemplazo / baja |
| ------ | ------------------------------------------ | --------- | ----------------------- |
| 1      | Pendiente Entrega Material de Empaque     | No        | No                      |
| 2      | Lista para confirmación de asignaciónes   | Sí → pasa a 3 | No                  |
| 3      | Lista para ejecución                       | No        | Sí                      |
| 4      | En Progreso                                | No        | Sí                      |
| 5      | Finalizada                                 | No        | No                      |

### Endpoints

Todos bajo `jwt.auth`, en `routes/weeklyplantasks.php`, en el grupo `// FUNCTIONALITYS`. Los atiende `WeeklyPlanTaskEmployeesController`.

| Método   | Ruta                                          | Body                                  | Respuesta                                                        |
| -------- | --------------------------------------------- | ------------------------------------- | ---------------------------------------------------------------- |
| `GET`    | `/weekly-plan-tasks/{id}/availableEmployees`  | —                                     | 200, `WeeklyPlanEmployeeResource::collection` de los candidatos   |
| `GET`    | `/weekly-plan-tasks/{id}/employees`           | —                                     | 200, `WeeklyPlanTaskEmployeeResource::collection` de las activas  |
| `POST`   | `/weekly-plan-tasks/{id}/confirmEmployees`    | ver abajo                             | 201, `data: null`                                                |
| `POST`   | `/weekly-plan-tasks/{id}/addEmployee`         | `weekly_plan_employee_id`             | 201, `data: null`                                                |
| `PATCH`  | `/weekly-plan-task-employees/{id}/replace`    | `weekly_plan_employee_id` (el que entra) | 200, `data: null`                                             |
| `DELETE` | `/weekly-plan-task-employees/{id}`            | —                                     | 200, `data: null`                                                |

Mensajes de éxito:

- Candidatos: `Candidatos Obtenidos Correctamente`.
- Asignados: `Personal Asignado Obtenido Correctamente`.
- Confirmar: `Personal Asignado Correctamente`.
- Alta: `Empleado Agregado Correctamente`.
- Reemplazo: `Empleado Reemplazado Correctamente`.
- Baja: `Empleado Retirado Correctamente`.

### Body de `confirmEmployees`

Todos los campos son opcionales. Un body vacío confirma a todos los candidatos tal cual.

```json
{
  "replacements": [
    { "weekly_plan_employee_id": 10, "replacement_weekly_plan_employee_id": 55 }
  ],
  "additions": [61, 62],
  "removals": [12]
}
```

`ConfirmWeeklyPlanTaskEmployeesRequest`:

```text
replacements                                         array    nullable
replacements.*.weekly_plan_employee_id               integer  required|distinct|exists:weekly_plan_employees,id
replacements.*.replacement_weekly_plan_employee_id   integer  required|distinct|exists:weekly_plan_employees,id
additions                                            array    nullable
additions.*                                          integer  distinct|exists:weekly_plan_employees,id
removals                                             array    nullable
removals.*                                           integer  distinct|exists:weekly_plan_employees,id
```

`AddWeeklyPlanTaskEmployeeRequest` y `ReplaceWeeklyPlanTaskEmployeeRequest`:

```text
weekly_plan_employee_id   integer   required|exists:weekly_plan_employees,id
```

Mensajes de validación en español, con el estilo de `CreateWeeklyPlanEmployeeRequest`.

### Reglas de negocio de `confirmEmployees`

Se acumulan **todos** los errores y se lanza un único `BadRequestError` con los mensajes unidos por `PHP_EOL`, igual que la carga Excel. Los empleados se identifican por `employees.code`.

- Tarea inexistente → `NotFoundError` (reutiliza `getWeeklyPlanTaskById`).
- Status distinto de `2` → `La tarea no está lista para confirmar asignaciones`. Se lanza solo, sin seguir validando.
- Ya existen filas activas → `La tarea ya tiene personal asignado`. Se lanza solo.
- `replacements.*.weekly_plan_employee_id` o `removals.*` que no es candidato → `El empleado 'X' no es candidato de la tarea`.
- Un candidato en `removals` y en `replacements` a la vez → `El empleado 'X' no puede quitarse y reemplazarse a la vez`.
- Reemplazo o alta de otro plan → `El empleado 'X' no pertenece al plan semanal de la tarea`.
- Un empleado que queda dos veces en la lista final (por ejemplo, un reemplazo que también es candidato no quitado, o un alta repetida con un reemplazo) → `El empleado 'X' está asignado más de una vez`.
- Lista final vacía → `La tarea debe tener al menos un empleado asignado`.

Si todo es válido, dentro de `DB::transaction`:

1. `insert` de todas las filas (candidatos sin quitar ni reemplazar, reemplazos y altas) con `created_at`/`updated_at`.
2. `$task->status = 3; $task->save();`, igual que `PackingMaterialTransactionsService`.

### Reglas de negocio individuales (alta, reemplazo, baja)

- Status de la tarea fuera de `3` o `4` → `BadRequestError('Solo se puede modificar el personal de una tarea lista para ejecución o en progreso')`.
- Fila inexistente o con soft delete (reemplazo y baja; `find` ya las excluye) → `NotFoundError('La asignación no existe')`.
- Empleado entrante de otro plan → `BadRequestError("El empleado 'X' no pertenece al plan semanal de la tarea")`.
- Empleado entrante con fila activa en la tarea → `BadRequestError("El empleado 'X' ya está asignado a la tarea")`.
- **Alta:** crea una fila con la posición del `WeeklyPlanEmployee` y `replaced_weekly_plan_employee_id = null`.
- **Reemplazo:** dentro de `DB::transaction`, hace `delete()` (soft delete) de la fila actual y crea una nueva con el `position_id` de la fila actual y `replaced_weekly_plan_employee_id` = `weekly_plan_employee_id` de la fila actual.
- **Baja:** `delete()` (soft delete). Si es la única fila activa → `BadRequestError('La tarea debe tener al menos un empleado asignado')`.

### `WeeklyPlanTaskEmployeeResource`

```php
[
    'id' => $this->id,
    'weekly_plan_employee_id' => $this->weekly_plan_employee_id,
    'name' => $this->weeklyPlanEmployee->employee->name,
    'code' => $this->weeklyPlanEmployee->employee->code,
    'position_id' => $this->position_id,
    'position' => $this->position->code,
    'replaced_weekly_plan_employee_id' => $this->replaced_weekly_plan_employee_id,
    'replaced_name' => $this->replacedWeeklyPlanEmployee?->employee->name,
    'replaced_code' => $this->replacedWeeklyPlanEmployee?->employee->code,
]
```

El Service carga con `with(['weeklyPlanEmployee.employee', 'position', 'replacedWeeklyPlanEmployee.employee'])` para evitar N+1. Los candidatos se cargan con `with(['employee', 'position'])` porque `WeeklyPlanEmployeeResource` los usa.

### Cambio en `WeeklyPlanEmployeesService`

`deleteWeeklyPlanEmployeeById`: si el empleado tiene filas en `weekly_plan_task_employees`, incluidas las de soft delete (`withTrashed()`), como asignado o como reemplazado → `BadRequestError('No se puede eliminar un empleado del plan con personal asignado en tareas')`. Sin esto, la FK lanza un error SQL (500).

---

## Plan de implementación

Cada paso deja el sistema funcional y es commiteable por separado.

1. **Migración y modelo.** `php artisan make:model WeeklyPlanTaskEmployee -m --no-interaction`. Columnas, FKs e índice del modelo de datos. Trait `SoftDeletes`, fillable y relaciones del modelo nuevo. `employees()` en `WeeklyPlanTask` y `taskAssignments()` en `WeeklyPlanEmployee`. Verificación: `php artisan migrate` sin error y `database-schema` muestra la tabla.
2. **Esqueleto de la feature.** `WeeklyPlanTaskEmployeesServiceInterface`, `WeeklyPlanTaskEmployeesService`, `WeeklyPlanTaskEmployeesProvider` registrado en `bootstrap/providers.php`, `WeeklyPlanTaskEmployeesController` vacío y `WeeklyPlanTaskEmployeeResource`. Verificación: `php artisan route:list` no falla.
3. **Candidatos.** `getAvailableEmployees(string $taskId)` en Interface y Service, acción en el controlador y ruta `GET /weekly-plan-tasks/{id}/availableEmployees`. Verificación manual: una tarea de `1REM1A` devuelve solo los empleados del plan con posición de esa línea y status `1`.
4. **Asignados.** `getTaskEmployees(string $taskId)` y ruta `GET /weekly-plan-tasks/{id}/employees`. Devuelve solo filas sin soft delete (scope por defecto de `SoftDeletes`). Verificación: tarea sin asignaciones → `data: []`.
5. **Confirmación sin excepciones.** `ConfirmWeeklyPlanTaskEmployeesRequest`, `confirmEmployees(string $taskId, array $data)` y ruta `POST /weekly-plan-tasks/{id}/confirmEmployees`. Validaciones de status y de filas activas existentes, `insert` de todos los candidatos y cambio a status `3` en `DB::transaction`, releyendo la tarea con `lockForUpdate()` antes de insertar. Verificación: body vacío en tarea con status `2` → filas creadas y status `3`.
6. **Confirmación con excepciones.** Aplicar `replacements`, `additions` y `removals` con todas las reglas de la sección anterior y errores acumulados. Verificación manual de cada caso de los criterios de aceptación.
7. **Alta individual.** `AddWeeklyPlanTaskEmployeeRequest`, `addEmployee(string $taskId, int $weeklyPlanEmployeeId)` y ruta `POST /weekly-plan-tasks/{id}/addEmployee`.
8. **Reemplazo individual.** `ReplaceWeeklyPlanTaskEmployeeRequest`, `replaceEmployee(string $assignmentId, int $weeklyPlanEmployeeId)` y ruta `PATCH /weekly-plan-task-employees/{id}/replace`.
9. **Baja individual.** `removeEmployee(string $assignmentId)` y ruta `DELETE /weekly-plan-task-employees/{id}`.
10. **Protección al borrar `WeeklyPlanEmployee`.** Validación en `WeeklyPlanEmployeesService::deleteWeeklyPlanEmployeeById`.
11. `vendor/bin/pint --dirty --format agent`.

---

## Criterios de aceptación

- [ ] `php artisan migrate` crea `weekly_plan_task_employees` con las FKs y la columna `deleted_at` y el índice `(weekly_plan_task_id, deleted_at)`.
- [ ] `php artisan route:list --path=weekly-plan-task` muestra las 6 rutas nuevas bajo `jwt.auth`.
- [ ] `availableEmployees` de una tarea de `1REM1A` devuelve solo `WeeklyPlanEmployee` del mismo plan con posición de esa línea; no incluye los de otras líneas, otros planes ni posiciones con `status = 0`.
- [ ] `confirmEmployees` con body vacío en status `2` crea una fila por candidato con su `position_id` actual, `replaced_weekly_plan_employee_id = null`, y deja la tarea en status `3`.
- [ ] `confirmEmployees` en status distinto de `2` responde 400 `La tarea no está lista para confirmar asignaciones` y no crea filas.
- [ ] Un reemplazo al confirmar crea la fila del entrante con la posición del reemplazado y `replaced_weekly_plan_employee_id` del reemplazado; el reemplazado no tiene fila.
- [ ] Un reemplazo puede ser un empleado del plan de otra línea.
- [ ] Un alta al confirmar crea una fila con la posición del `WeeklyPlanEmployee` del agregado.
- [ ] Un `removals` al confirmar hace que ese candidato no tenga fila.
- [ ] Reemplazo o alta de otro plan → 400 `El empleado 'X' no pertenece al plan semanal de la tarea`.
- [ ] `removals` o reemplazado que no es candidato → 400 `El empleado 'X' no es candidato de la tarea`.
- [ ] Un reemplazo que también es candidato no quitado → 400 `El empleado 'X' está asignado más de una vez`.
- [ ] Quitar a todos sin altas → 400 `La tarea debe tener al menos un empleado asignado`.
- [ ] Un body con varios errores devuelve todos en una sola respuesta y no crea filas ni cambia el status.
- [ ] Cambiar la posición de un `WeeklyPlanEmployee` después de confirmar no cambia el `position` que devuelve `GET /weekly-plan-tasks/{id}/employees`.
- [ ] `addEmployee` en status `3` o `4` crea la fila; en `1`, `2` o `5` responde 400.
- [ ] `addEmployee` de un empleado ya activo en la tarea → 400 `El empleado 'X' ya está asignado a la tarea`.
- [ ] `replace` hace soft delete de la fila original (`deleted_at` informado) y crea una nueva con la misma posición y `replaced_weekly_plan_employee_id` del saliente.
- [ ] `replace` o `DELETE` sobre una fila con soft delete → 404 `La asignación no existe`.
- [ ] `DELETE` pone `deleted_at` y la fila deja de salir en `GET /weekly-plan-tasks/{id}/employees`, pero sigue en BD.
- [ ] `DELETE` de la única fila activa → 400 `La tarea debe tener al menos un empleado asignado`.
- [ ] Un empleado quitado puede volver a agregarse con `addEmployee`.
- [ ] Borrar un `WeeklyPlanEmployee` con asignaciones en tareas → 400 con el mensaje de la spec, no 500.
- [ ] Borrar una `WeeklyPlanTask` borra sus filas de `weekly_plan_task_employees`.
- [ ] `GET /weekly-plan-tasks/{id}/employees` no hace consultas N+1 (una consulta por relación cargada).
- [ ] `vendor/bin/pint --dirty --format agent` no reporta cambios pendientes.

---

## Decisiones

- **Sí:** candidatos por FK: `position.line_id` = línea de la tarea. Usa relaciones existentes, sin comparar prefijos de códigos.
- **No:** prefijo de `positions.code` o `employees.code` contra `lines.code`. Depende de que la codificación sea consistente y se rompe con cualquier excepción.
- **Sí:** solo posiciones con `status = 1`. Igual que `LinesService`.
- **Sí:** guardar `position_id` en cada asignación. La posición del `WeeklyPlanEmployee` puede cambiar y el histórico de tareas no debe moverse.
- **No:** leer la posición siempre desde el `WeeklyPlanEmployee`. Un cambio en el plan reescribiría tareas ya ejecutadas.
- **Sí:** reemplazos y altas solo con `WeeklyPlanEmployee` del mismo plan, de cualquier línea. Todo el que trabaja debe estar en el listado principal del plan.
- **No:** reemplazar con cualquier `Employee` ni crear su `WeeklyPlanEmployee` automáticamente.
- **Sí:** el reemplazo hereda la posición del reemplazado en esa tarea. Su `WeeklyPlanEmployee` no cambia.
- **Sí:** el alta usa la posición de su `WeeklyPlanEmployee`. Mismo criterio que los candidatos.
- **Sí:** el backend calcula los candidatos al confirmar; el frontend solo manda las excepciones. Evita que el frontend mande una lista desactualizada.
- **Sí:** confirmar solo en status `2` y pasar a `3` en la misma transacción. Coincide con los mensajes actuales de `WeeklyPlanTaskResource`.
- **Sí:** confirmar una sola vez. Después solo hay operaciones individuales.
- **No:** re-confirmar borrando y recreando la lista. Perdería el historial.
- **Sí:** alta, reemplazo y baja individuales en status `3` y `4`. Cubre ausencias detectadas antes o durante la ejecución.
- **Sí:** historial por filas con `SoftDeletes`. Guarda la cadena completa de reemplazos y es la convención de Laravel: las consultas excluyen las filas borradas sin filtros manuales.
- **No:** editar la fila en el reemplazo. Solo guardaría el último cambio.
- **No:** columna `removed_at` propia ni booleana `active`. `deleted_at` dice lo mismo y Eloquent ya lo filtra.
- **No:** `forceDelete` en esta feature. Las filas solo se borran físicamente con la tarea (`cascadeOnDelete`).
- **Sí:** `replaced_weekly_plan_employee_id` nullable. Sirve para saber quién faltó y para la futura spec del biométrico.
- **Sí:** mínimo un empleado activo, al confirmar y al dar de baja. Una tarea en ejecución sin personal no tiene sentido.
- **Sí:** errores acumulados en un solo `BadRequestError` al confirmar. El usuario corrige todo de una vez, igual que en la carga Excel.
- **Sí:** las respuestas de escritura devuelven `data: null`. El frontend vuelve a pedir `GET /weekly-plan-tasks/{id}/employees`.
- **Sí:** rutas camelCase bajo `/weekly-plan-tasks/{id}/…`, igual que `splitTask` y `packingMaterialItems`.
- **No:** `apiResource` para `weekly-plan-task-employees`. No hay CRUD libre: crear y editar siguen reglas del flujo.
- **Sí:** `cascadeOnDelete` desde la tarea. Si se borra la tarea, su personal no tiene a qué apuntar.
- **Sí:** bloquear el borrado de un `WeeklyPlanEmployee` con asignaciones. Sin esto la FK devuelve 500.
- **No:** validación de asistencia con el biométrico. Va en su propia spec.
- **No:** tests automatizados. Consistente con SPEC 01–05; el repo no tiene `tests/`.

---

## Riesgos

| Riesgo | Mitigación |
| ------ | ---------- |
| Hoy no hay datos en `positions`, `employees` ni `weekly_plan_employees`. Sin posiciones ligadas a la línea, no hay candidatos. | Cargar posiciones con la carga Excel de SPEC 04 y el personal del plan antes de probar. La confirmación con cero candidatos solo pasa si hay `additions`. |
| Dos `WeeklyPlanEmployee` del plan con la misma posición aparecen ambos como candidatos. | Se acepta: el usuario quita a uno al confirmar. No se agrega unicidad de posición por plan en esta spec. |
| Dos usuarios confirman la misma tarea al mismo tiempo y se duplican las filas. | Dentro de la transacción se vuelve a leer la tarea con `lockForUpdate()` y se revalida el status `2` antes de insertar. |
| Cambiar a status `3` dispara `WeeklyPlanTaskObserver::updated`. | `status` no está en `LOGGED_FIELDS`: no genera log ni correo. Es el mismo caso que `PackingMaterialTransactionsService`. |
| El `insert` masivo no dispara eventos de `WeeklyPlanTaskEmployee`. | El modelo no tiene observer. Si se agrega uno, revisar la confirmación. |
| `deleteWeeklyPlanTaskById` de una tarea con personal borra también su historial. | Se acepta por `cascadeOnDelete`. Borrar tareas ejecutadas es un problema aparte. |

---

## Lo que **no** está en esta spec

- Validación de asistencia con el biométrico.
- Endpoint de historial de cambios de personal.
- Paginación de candidatos o asignados.
- Asignar empleados fuera de `weekly_plan_employees` del plan.
- Elegir posición para un empleado agregado.
- Validación de empleados en dos tareas simultáneas.
- Horas o pago por empleado y tarea.
- Copia de personal en `splitTask`.
- Logs o correos de cambios de personal.
- Personal dentro de `WeeklyPlanTaskResource`.
- Tests automatizados.

Cada uno, si llega, va en su propia spec.
