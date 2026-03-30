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
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('current_workspace_id');
        });

        // Migrate existing language_id to locale code
        DB::statement('
            UPDATE users
            SET locale = COALESCE(
                (SELECT code FROM languages WHERE languages.id = users.language_id),
                \'en\'
            )
        ');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropColumn('language_id');
        });

        Schema::dropIfExists('languages');
    }

    public function down(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('language_id')->nullable()->after('current_workspace_id');
            $table->foreign('language_id')->references('id')->on('languages')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
