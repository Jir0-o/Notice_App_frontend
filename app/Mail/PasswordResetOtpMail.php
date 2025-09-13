<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
// removed: use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public ?User $user = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password reset code',
            tags: ['password-reset', 'otp'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password_reset_otp',
            with: [
                'name' => $this->user?->name ?? 'there',
                'otp'  => $this->otp,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}