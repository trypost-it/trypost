<?php

namespace App\Notifications;

use App\Models\SocialAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDisconnectedNotification extends Notification
{
    public function __construct(
        public SocialAccount $account
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reconnectUrl = route('workspaces.accounts', $this->account->workspace_id);
        $platformName = $this->account->platform->label();
        $accountName = $this->account->display_name ?? $this->account->username;

        return (new MailMessage)
            ->subject("Your {$platformName} account needs to be reconnected")
            ->greeting('Hello!')
            ->line("Your **{$platformName}** account **{$accountName}** has been disconnected from TryPost.")
            ->line('This may have happened because:')
            ->line('- Your access token expired')
            ->line('- You revoked access to TryPost')
            ->line('- There was an authentication error')
            ->line('Please reconnect your account to continue scheduling and publishing posts.')
            ->action('Reconnect Account', $reconnectUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'account_id' => $this->account->id,
            'platform' => $this->account->platform->value,
            'workspace_id' => $this->account->workspace_id,
        ];
    }
}
