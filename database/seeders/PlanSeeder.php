<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => Slug::Starter,
                'name' => 'Starter',
                'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY'),
                'monthly_price' => 1900,
                'yearly_price' => 19000,
                'social_account_limit' => 5,
                'member_limit' => 1,
                'workspace_limit' => 1,
                'ai_images_limit' => 50,
                'data_retention_days' => 30,
                'sort' => 1,
            ],
            [
                'slug' => Slug::Plus,
                'name' => 'Plus',
                'stripe_monthly_price_id' => env('STRIPE_PLUS_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_PLUS_YEARLY'),
                'monthly_price' => 2900,
                'yearly_price' => 29000,
                'social_account_limit' => 10,
                'member_limit' => 5,
                'workspace_limit' => 5,
                'ai_images_limit' => 150,
                'data_retention_days' => 60,
                'sort' => 2,
            ],
            [
                'slug' => Slug::Pro,
                'name' => 'Pro',
                'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY'),
                'monthly_price' => 4900,
                'yearly_price' => 49000,
                'social_account_limit' => 30,
                'member_limit' => 15,
                'workspace_limit' => 15,
                'ai_images_limit' => 500,
                'data_retention_days' => 90,
                'sort' => 3,
            ],
            [
                'slug' => Slug::Max,
                'name' => 'Max',
                'stripe_monthly_price_id' => env('STRIPE_MAX_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_MAX_YEARLY'),
                'monthly_price' => 9900,
                'yearly_price' => 99000,
                'social_account_limit' => 100,
                'member_limit' => 20,
                'workspace_limit' => 50,
                'ai_images_limit' => 2000,
                'data_retention_days' => 730,
                'sort' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
