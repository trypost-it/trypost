<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')
            ->whereIn('slug', ['starter', 'plus', 'pro', 'max'])
            ->update([
                'allowed_networks' => null,
                'can_use_ai' => true,
                'can_use_analytics' => true,
                'scheduled_posts_limit' => null,
            ]);

        DB::table('plans')->updateOrInsert(
            ['slug' => 'free'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Free',
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'social_account_limit' => 1,
                'member_limit' => 1,
                'workspace_limit' => 1,
                'monthly_credits_limit' => 0,
                'allowed_networks' => json_encode(['linkedin', 'instagram', 'facebook', 'tiktok', 'youtube', 'threads', 'pinterest', 'bluesky', 'mastodon']),
                'can_use_ai' => false,
                'can_use_analytics' => false,
                'scheduled_posts_limit' => 15,
                'sort' => 0,
                'is_archived' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('plans')->where('slug', 'free')->delete();
    }
};
