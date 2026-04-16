<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_ai_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('workspace_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->string('type'); // image, video, audio
            $table->string('provider')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index(['account_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_ai_usages');
    }
};
