<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('platform');
            $table->text('content');
            $table->json('slides')->nullable();
            $table->integer('image_count')->default(0);
            $table->json('image_keywords')->nullable();
            $table->timestamps();

            $table->index(['platform', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_templates');
    }
};
