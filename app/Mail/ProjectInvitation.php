<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ProjectInvitation extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $projectName,
        public readonly string $role,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Convite para o projeto {$this->projectName} no Ganttist");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-invitation');
    }
}
