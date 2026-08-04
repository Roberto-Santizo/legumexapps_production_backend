<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyPlanTaskChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Human readable label of every tracked field.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'boxes' => 'Cajas',
        'operation_date' => 'Fecha de operación',
        'line_sku_id' => 'Línea/SKU',
    ];

    /**
     * Create a new message instance.
     *
     * @param  'created'|'updated'  $event
     * @param  array<int, array{field: string, old: ?string, new: ?string}>  $changes
     */
    public function __construct(
        protected string $event,
        protected int $taskId,
        protected string $userName,
        protected Carbon $changedAt,
        protected array $changes,
        protected ?string $weeklyPlan = null,
        protected ?string $productName = null,
        protected ?string $lineName = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->event === 'created'
            ? "Nueva tarea de plan semanal #{$this->taskId}"
            : "Cambios en la tarea de plan semanal #{$this->taskId}";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-plan-task-changed',
            with: [
                'event' => $this->event,
                'taskId' => $this->taskId,
                'userName' => $this->userName,
                'changedAt' => $this->changedAt,
                'changes' => $this->labelledChanges(),
                'weeklyPlan' => $this->weeklyPlan,
                'productName' => $this->productName,
                'lineName' => $this->lineName,
                'logoUrl' => config('mail.logo_url'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Attach the Spanish label of each tracked field to the change list.
     *
     * @return array<int, array{field: string, label: string, old: ?string, new: ?string}>
     */
    private function labelledChanges(): array
    {
        return array_map(static fn (array $change): array => [
            'field' => $change['field'],
            'label' => self::FIELD_LABELS[$change['field']] ?? $change['field'],
            'old' => $change['old'],
            'new' => $change['new'],
        ], $this->changes);
    }
}
