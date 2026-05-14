<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['can_use_ai', 'can_use_analytics', 'blocked_networks', 'scheduled_posts_limit']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('can_use_ai')->default(true)->after('monthly_credits_limit');
            $table->boolean('can_use_analytics')->default(true)->after('can_use_ai');
            $table->json('blocked_networks')->nullable()->after('can_use_analytics');
            $table->integer('scheduled_posts_limit')->nullable()->after('blocked_networks');
        });
    }
};
