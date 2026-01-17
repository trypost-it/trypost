<?php

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
        Schema::create('post_platforms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('social_account_id');
            $table->string('platform');
            $table->text('content')->nullable();
            $table->string('status')->default('pending');
            $table->string('platform_post_id')->nullable();
            $table->boolean('enabled');
            $table->string('platform_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('social_account_id')->references('id')->on('social_accounts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_platforms');
    }
};
