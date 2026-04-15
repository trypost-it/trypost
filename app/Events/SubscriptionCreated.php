<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Account $account) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->account->owner_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => 'success',
            'message' => 'Subscription created successfully',
        ];
    }
}
