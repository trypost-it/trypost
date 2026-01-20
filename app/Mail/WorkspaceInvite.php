<?php

namespace App\Mail;

use App\Models\WorkspaceInvite as WorkspaceInviteModel;
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
        public WorkspaceInviteModel $invite
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invite->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-invite',
            with: [
                'title' => "You've been invited to join {$this->invite->workspace->name}",
                'previewText' => "You've been invited to join {$this->invite->workspace->name}",
                'invite' => $this->invite,
                'url' => route('invites.show', $this->invite->id),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
