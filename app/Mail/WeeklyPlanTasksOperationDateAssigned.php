<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyPlanTasksOperationDateAssigned extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<int, array{id: int, oldOperationDate: ?string, productName: ?string, lineName: ?string, weeklyPlan: ?string}>  $tasks
     */
    public function __construct(
        protected string $operationDate,
        protected string $userName,
        protected Carbon $changedAt,
        protected array $tasks,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fecha de operación asignada a '.count($this->tasks).' tareas',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-plan-tasks-operation-date-assigned',
            with: [
                'operationDate' => $this->operationDate,
                'userName' => $this->userName,
                'changedAt' => $this->changedAt,
                'tasks' => $this->tasks,
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
}
