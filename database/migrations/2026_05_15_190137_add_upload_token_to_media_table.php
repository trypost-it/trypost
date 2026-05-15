<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medias', function (Blueprint $table): void {
            $table->uuid('upload_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table): void {
            $table->dropUnique(['upload_token']);
            $table->dropColumn('upload_token');
        });
    }
};
