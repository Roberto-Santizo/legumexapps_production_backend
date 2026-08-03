<x-mail::message>
# {{ $event === 'created' ? "Nueva tarea de plan semanal #{$taskId}" : "Cambios en la tarea de plan semanal #{$taskId}" }}

@if ($event === 'created')
**{{ $userName }}** creó la tarea **#{{ $taskId }}** el {{ $changedAt->format('d/m/Y H:i') }}.
@else
**{{ $userName }}** modificó la tarea **#{{ $taskId }}** el {{ $changedAt->format('d/m/Y H:i') }}.
@endif

<x-mail::table>
| Campo | Antes | Después |
| :---- | :---- | :------ |
@foreach ($changes as $change)
| {{ $change['label'] }} | {{ $change['old'] ?? '—' }} | {{ $change['new'] ?? '—' }} |
@endforeach
</x-mail::table>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
