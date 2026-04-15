<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invite $invite
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invite->account->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-invite',
            with: [
                'title' => "You've been invited to join {$this->invite->account->name}",
                'previewText' => "You've been invited to join {$this->invite->account->name}",
                'invite' => $this->invite,
                'url' => route('app.invites.show', $this->invite),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
