<?php

namespace App\Mail;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        $shop = CompanyProfile::current()->name ?: config('app.name');

        return new Envelope(
            subject: __('Welcome to :app — your login details', ['app' => $shop]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-welcome',
            with: [
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'shopName' => CompanyProfile::current()->name ?: config('app.name'),
                'loginUrl' => route('login'),
                'forgotUrl' => route('password.request'),
            ],
        );
    }
}
