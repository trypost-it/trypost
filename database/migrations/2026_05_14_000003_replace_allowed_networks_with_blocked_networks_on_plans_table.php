<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('blocked_networks')->nullable()->after('monthly_credits_limit');
        });

        // Free plan blocks X; paid plans block nothing (null)
        DB::table('plans')
            ->where('slug', 'free')
            ->update(['blocked_networks' => json_encode(['x'])]);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('allowed_networks');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('allowed_networks')->nullable()->after('monthly_credits_limit');
        });

        // Restore the 9-network whitelist for free plan
        DB::table('plans')
            ->where('slug', 'free')
            ->update(['allowed_networks' => json_encode([
                'linkedin', 'instagram', 'facebook', 'tiktok', 'youtube', 'threads', 'pinterest', 'bluesky', 'mastodon',
            ])]);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('blocked_networks');
        });
    }
};
