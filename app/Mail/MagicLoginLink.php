<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class MagicLoginLink extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $url, public readonly string $pin)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu acesso ao Ganttist');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.magic-login-link');
    }
}
