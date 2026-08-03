<x-mail::message>
# Fecha de operación asignada a {{ count($tasks) }} tareas

**{{ $userName }}** asignó la fecha de operación **{{ $operationDate }}** el {{ $changedAt->format('d/m/Y H:i') }}.

<x-mail::table>
| Tarea | Fecha anterior |
| :---- | :------------- |
@foreach ($tasks as $task)
| #{{ $task['id'] }} | {{ $task['oldOperationDate'] ?? '—' }} |
@endforeach
</x-mail::table>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
