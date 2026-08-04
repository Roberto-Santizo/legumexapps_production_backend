@php
    $isCreated = $event === 'created';
    $eyebrow = $isCreated ? 'Tarea creada' : 'Tarea actualizada';
    $headline = $productName ?: 'Tarea de plan semanal';
    $preheader = trim(($weeklyPlan ? $weeklyPlan.' · ' : '').$headline.' · '.$userName);

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
    <title>{{ $eyebrow }} · {{ $headline }}</title>
    <style>
        @media only screen and (max-width: 480px) {
            .lx-shell { padding: 16px 12px !important; }
            .lx-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .lx-headline { font-size: 21px !important; }
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
                                        {{ $weeklyPlan ?: 'Plan semanal' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Producto y tarea --}}
                    <tr>
                        <td class="lx-pad" style="padding:32px 32px 24px 32px;">
                            <div style="font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:{{ $ink }};">{{ $eyebrow }}</div>
                            <h1 class="lx-headline" style="margin:10px 0 0 0; font-family:{{ $sans }}; font-size:25px; line-height:1.2; font-weight:700; letter-spacing:-0.02em; color:{{ $ink }};">{{ $headline }}</h1>
                            <div style="margin-top:8px; font-family:{{ $mono }}; font-size:13px; color:{{ $muted }};">{{ $lineName ? $lineName.' · ' : '' }}Tarea #{{ $taskId }}</div>
                        </td>
                    </tr>

                    {{-- Cambios --}}
                    <tr>
                        <td class="lx-pad" style="padding:0 32px 8px 32px;">
                            <div style="font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:6px; border-bottom:1px solid {{ $line }};">
                                {{ $isCreated ? 'Datos registrados' : 'Cambios' }}
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach ($changes as $change)
                                    <tr>
                                        <td style="padding:16px 0 16px 16px; border-left:2px solid {{ $accent }};{{ $loop->first ? '' : ' border-top:1px solid '.$line.';' }}">
                                            <div style="font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:{{ $muted }};">{{ $change['label'] }}</div>
                                            <div style="margin-top:7px; font-family:{{ $mono }}; font-size:15px; line-height:1.4; color:{{ $ink }};">
                                                @unless ($isCreated)
                                                    <span style="color:{{ $muted }}; text-decoration:line-through;">{{ $change['old'] ?? '—' }}</span>
                                                    <span style="color:{{ $muted }}; padding:0 8px;">&rarr;</span>
                                                @endunless
                                                <span style="display:inline-block; padding:3px 9px; background-color:{{ $accentSoft }}; color:{{ $accent }}; font-weight:700; border-radius:3px;">{{ $change['new'] ?? '—' }}</span>
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
