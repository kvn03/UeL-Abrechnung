<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Konstruktor: Hier kommen die Daten rein (User & Token)
     */
    public function __construct(
        public User $user,
        public string $token
    ) {}

    /**
     * Betreff der E-Mail
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen! Bitte Passwort setzen',
        );
    }

    /**
     * Inhalt und Template definieren
     */
    public function content(): Content
    {
        $frontendBase = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'https://uel-abrechnung-frontend-production.up.railway.app')), '/');
        $frontendUrl = $frontendBase . '/set-password';

        $queryParams = http_build_query([
            'token' => $this->token,
            'email' => $this->user->email,
        ]);

        return new Content(
            view: 'emails.invite', // Verweist auf resources/views/emails/invite.blade.php
            with: [
                'link' => $frontendUrl . '?' . $queryParams,
                'user' => $this->user,
            ]
        );
    }
}
