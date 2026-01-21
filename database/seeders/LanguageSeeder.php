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
        Language::create(['name' => 'English (US)', 'code' => 'en-US']);
        Language::create(['name' => 'Português (BR)', 'code' => 'pt-BR']);
    }
}
