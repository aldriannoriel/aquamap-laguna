<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AquaMap Laguna - Email Verification Code'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-otp'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
