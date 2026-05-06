<?php

declare(strict_types=1);

namespace App\Models;

use App\Features\MemberLimit;
use App\Features\MonthlyCreditsLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Models\Traits\HasUsage;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Pennant\Feature;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Billable, HasFactory, HasUsage, HasUuids;

    public const SUBSCRIPTION_NAME = 'default';

    protected $fillable = [
        'owner_id',
        'name',
        'billing_email',
        'plan_id',
    ];

    protected static function booted(): void
    {
        static::updated(function (Account $account): void {
            if ($account->wasChanged('plan_id')) {
                Feature::for($account)->forget([
                    WorkspaceLimit::class,
                    SocialAccountLimit::class,
                    MemberLimit::class,
                    MonthlyCreditsLimit::class,
                ]);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    public function isOnTrial(): bool
    {
        return $this->subscription(self::SUBSCRIPTION_NAME)?->onTrial() ?? false;
    }

    /**
     * Returns the displayable card for the billing UI. Falls back to the first
     * attached payment method when the customer has no `invoice_settings.default_payment_method`
     * (Stripe Checkout trials anchor the card to the subscription, not the customer).
     *
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    public function displayablePaymentMethod(): ?array
    {
        $paymentMethod = $this->defaultPaymentMethod() ?? $this->paymentMethods()->first();
        $card = $paymentMethod?->card;

        if (! $card) {
            return null;
        }

        return [
            'brand' => $card->brand,
            'last4' => $card->last4,
            'exp_month' => $card->exp_month,
            'exp_year' => $card->exp_year,
        ];
    }

    public function stripeEmail(): string
    {
        return $this->billing_email ?? $this->owner?->email ?? '';
    }

    public function stripeName(): string
    {
        return $this->name;
    }
}
