@php
    $formatDate = static function (?string $value): ?string {
        if ($value === null) {
            return null;
        }

        return rescue(static fn (): string => \Illuminate\Support\Carbon::parse($value)->format('d/m/Y'), $value, false);
    };

    $taskCount = count($tasks);
    $countLabel = $taskCount === 1 ? '1 tarea actualizada' : $taskCount.' tareas actualizadas';
    $operationDateLabel = $formatDate($operationDate);

    $weeklyPlans = array_values(array_unique(array_filter(array_column($tasks, 'weeklyPlan'))));
    $headerPlan = count($weeklyPlans) === 1 ? $weeklyPlans[0] : 'Plan semanal';
    $showPlanPerTask = count($weeklyPlans) > 1;

    $preheader = $countLabel.' · '.$operationDateLabel.' · '.$userName;

    $ink = '#12211A';
    $muted = '#6E7B74';
    $line = '#E4E9E4';
    $paper = '#EEF1EC';
    $accent = '#2F6F4E';
    $accentSoft = '#EAF2ED';

    $sans = '-apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif';
    $mono = '"SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Fecha de operación asignada · {{ $operationDateLabel }}</title>
    <style>
        @media only screen and (max-width: 480px) {
            .lx-shell { padding: 16px 12px !important; }
            .lx-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .lx-date { font-size: 20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $paper }};">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $paper }};">
        <tr>
            <td class="lx-shell" align="center" style="padding:32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:560px; background-color:#FFFFFF; border:1px solid {{ $line }}; border-radius:4px;">

                    {{-- Encabezado: logo y plan semanal --}}
                    <tr>
                        <td class="lx-pad" style="padding:22px 32px; border-bottom:1px solid {{ $line }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        @if (! empty($logoUrl))
                                            <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" height="28" style="display:block; height:28px; width:auto; border:0;">
                                        @else
                                            <span style="font-family:{{ $sans }}; font-size:15px; font-weight:700; letter-spacing:-0.01em; color:{{ $ink }};">{{ config('app.name') }}</span>
                                        @endif
                                    </td>
                                    <td align="right" valign="middle" style="font-family:{{ $mono }}; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:{{ $muted }};">
                                        {{ $headerPlan }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Nueva fecha de operación --}}
                    <tr>
                        <td class="lx-pad" style="padding:32px 32px 24px 32px;">
                            <div style="font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:{{ $ink }};">Fecha de operación asignada</div>
                            <div style="margin-top:12px;">
                                <span class="lx-date" style="display:inline-block; padding:8px 14px; background-color:{{ $accentSoft }}; color:{{ $accent }}; font-family:{{ $mono }}; font-size:24px; font-weight:700; letter-spacing:-0.01em; border-radius:4px;">{{ $operationDateLabel }}</span>
                            </div>
                            <div style="margin-top:12px; font-family:{{ $mono }}; font-size:13px; color:{{ $muted }};">{{ $countLabel }}</div>
                        </td>
                    </tr>

                    {{-- Tareas afectadas --}}
                    <tr>
                        <td class="lx-pad" style="padding:0 32px 8px 32px;">
                            <div style="font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:6px; border-bottom:1px solid {{ $line }};">
                                Tareas afectadas
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach ($tasks as $task)
                                    @php
                                        $meta = array_filter([
                                            $task['lineName'] ?? null,
                                            'Tarea #'.$task['id'],
                                            $showPlanPerTask ? ($task['weeklyPlan'] ?? null) : null,
                                        ]);
                                        $previousDate = $formatDate($task['oldOperationDate'] ?? null);
                                    @endphp
                                    <tr>
                                        <td style="padding:16px 0 16px 16px; border-left:2px solid {{ $accent }};{{ $loop->first ? '' : ' border-top:1px solid '.$line.';' }}">
                                            <div style="font-family:{{ $sans }}; font-size:15px; font-weight:600; line-height:1.3; color:{{ $ink }};">{{ $task['productName'] ?? 'Producto sin asignar' }}</div>
                                            <div style="margin-top:5px; font-family:{{ $mono }}; font-size:12px; color:{{ $muted }};">{{ implode(' · ', $meta) }}</div>
                                            <div style="margin-top:7px; font-family:{{ $mono }}; font-size:13px; color:{{ $muted }};">
                                                Fecha anterior:
                                                @if ($previousDate)
                                                    <span style="text-decoration:line-through;">{{ $previousDate }}</span>
                                                @else
                                                    <span>sin fecha</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Autor y fecha del cambio --}}
                    <tr>
                        <td class="lx-pad" style="padding:24px 32px 30px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid {{ $line }};">
                                <tr>
                                    <td width="45%" style="padding:16px 0 0 0; font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:{{ $muted }};">Registrado por</td>
                                    <td align="right" style="padding:16px 0 0 0; font-family:{{ $sans }}; font-size:14px; font-weight:600; color:{{ $ink }};">{{ $userName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0 0 0; font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:{{ $muted }};">Fecha del cambio</td>
                                    <td align="right" style="padding:8px 0 0 0; font-family:{{ $mono }}; font-size:14px; color:{{ $ink }};">{{ $changedAt->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div style="max-width:560px; margin-top:16px; font-family:{{ $sans }}; font-size:11px; line-height:1.5; color:{{ $muted }};">
                    Notificación automática de {{ config('app.name') }}. No respondas a este correo.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
