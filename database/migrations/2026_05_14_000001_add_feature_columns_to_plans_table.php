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
            $table->json('allowed_networks')->nullable()->after('monthly_credits_limit');
            $table->boolean('can_use_ai')->default(true)->after('allowed_networks');
            $table->boolean('can_use_analytics')->default(true)->after('can_use_ai');
            $table->integer('scheduled_posts_limit')->nullable()->after('can_use_analytics');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['allowed_networks', 'can_use_ai', 'can_use_analytics', 'scheduled_posts_limit']);
        });
    }
};
