<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class PassportSeeder extends Seeder
{
    public function run(ClientRepository $clients): void
    {
        try {
            $clients->personalAccessClient('users');

            return;
        } catch (\RuntimeException) {
            // No client yet — fall through to create.
        }

        $clients->createPersonalAccessGrantClient(name: 'TryPost Personal Access Client');
    }
}
