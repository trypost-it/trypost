<?php

namespace App\Mail;

use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WorkspaceConnectionsDisconnected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, SocialAccount>  $disconnectedAccounts
     */
    public function __construct(
        public Workspace $workspace,
        public Collection $disconnectedAccounts
    ) {
        $this->locale = $this->workspace->owner?->language?->code ?? 'en';
    }

    public function envelope(): Envelope
    {
        $count = $this->disconnectedAccounts->count();

        return new Envelope(
            subject: trans_choice('mail.workspace_connections_disconnected.subject', $count, [
                'count' => $count,
                'workspace' => $this->workspace->name,
            ], $this->locale),
        );
    }

    public function content(): Content
    {
        $count = $this->disconnectedAccounts->count();
        $workspaceName = $this->workspace->name;
        $locale = $this->locale;

        return new Content(
            view: 'mail.workspace-connections-disconnected',
            with: [
                'title' => __('mail.workspace_connections_disconnected.title', [], $locale),
                'previewText' => trans_choice('mail.workspace_connections_disconnected.subject', $count, [
                    'count' => $count,
                    'workspace' => $workspaceName,
                ], $locale),
                'intro' => __('mail.workspace_connections_disconnected.intro', ['workspace' => $workspaceName], $locale),
                'reasonsTitle' => __('mail.workspace_connections_disconnected.reasons_title', [], $locale),
                'reasonExpired' => __('mail.workspace_connections_disconnected.reason_expired', [], $locale),
                'reasonRevoked' => __('mail.workspace_connections_disconnected.reason_revoked', [], $locale),
                'reasonChanged' => __('mail.workspace_connections_disconnected.reason_changed', [], $locale),
                'reconnectCta' => __('mail.workspace_connections_disconnected.reconnect_cta', [], $locale),
                'buttonText' => __('mail.workspace_connections_disconnected.button', [], $locale),
                'workspace' => $this->workspace,
                'disconnectedAccounts' => $this->disconnectedAccounts,
                'url' => route('accounts'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
