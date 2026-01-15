<?php

namespace App\Notifications;

use App\Models\WorkspaceInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WorkspaceInvite $invite
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = route('invites.accept', $this->invite->token);

        return (new MailMessage)
            ->subject("Convite para o workspace {$this->invite->workspace->name}")
            ->greeting('Olá!')
            ->line("{$this->invite->inviter->name} convidou você para colaborar no workspace **{$this->invite->workspace->name}**.")
            ->line("Você foi convidado como **{$this->invite->role->label()}**.")
            ->action('Aceitar Convite', $acceptUrl)
            ->line('Este convite expira em 7 dias.')
            ->salutation('Obrigado por usar TryPost!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'workspace_id' => $this->invite->workspace_id,
            'workspace_name' => $this->invite->workspace->name,
            'inviter_name' => $this->invite->inviter->name,
            'role' => $this->invite->role->value,
        ];
    }
}
