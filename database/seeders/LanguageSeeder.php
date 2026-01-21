<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Language::create(['name' => 'English', 'code' => 'en']);
        Language::create(['name' => 'Português', 'code' => 'pt-br']);
    }
}
