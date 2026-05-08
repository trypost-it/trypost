<?php

declare(strict_types=1);

use App\Enums\Workspace\ImageStyle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('image_style')
                ->default(ImageStyle::DEFAULT->value)
                ->after('brand_font');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('image_style');
        });
    }
};
