<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeployStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $status,
        public readonly string $source,
        public readonly string $detail,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->status === 'success' ? 'succeeded' : 'failed';
        $prefix = $this->status === 'success' ? '✅' : '❌';

        return new Envelope(
            subject: "{$prefix} {$this->appUrl} deploy {$label} ({$this->source})",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.deploy-status',
        );
    }
}
