<?php

namespace App\Helpers;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailHandler
{
    /**
     * Send a mailable to the configured weekly plan task recipients.
     *
     * Returns silently when no recipient is configured, and never lets a
     * delivery failure bubble up into the operation that triggered it.
     */
    public static function notifyWeeklyPlanTaskRecipients(Mailable $mailable): void
    {
        $recipients = config('mail.weekly_plan_task_recipients');

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de notificación de tarea de plan semanal.', [
                'mailable' => $mailable::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
