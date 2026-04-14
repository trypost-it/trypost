<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['workspace_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'stripe_status']);
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'stripe_status']);
        });
    }
};
