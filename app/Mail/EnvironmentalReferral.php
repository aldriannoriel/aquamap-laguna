<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EnvironmentalReferral extends Mailable
{
    use Queueable;

    public function __construct(public array $details, public string $reference, private ?string $photoBytes = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->details['test'] ? 'AquaMap Laguna delivery test: ' : 'AquaMap Laguna environmental referral: ').$this->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.referral', text: 'mail.referral-text');
    }

    public function attachments(): array
    {
        return $this->photoBytes === null ? [] : [Attachment::fromData(fn (): string => $this->photoBytes, 'approved-supporting-photo.jpg')->withMime('image/jpeg')];
    }
}
