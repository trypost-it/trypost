<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Plan\Slug;
use App\Models\Account;
use App\Models\Plan;
use Illuminate\Console\Command;

class BackfillFreePlanCommand extends Command
{
    protected $signature = 'accounts:backfill-free-plan {--dry-run : Show what would change without modifying data}';

    protected $description = 'Assigns the Free plan to all accounts with a NULL plan_id. Idempotent.';

    public function handle(): int
    {
        $freePlan = Plan::where('slug', Slug::Free)->first();

        if (! $freePlan) {
            $this->error('Free plan not found. Insert the Free plan row before running this command.');

            return self::FAILURE;
        }

        $query = Account::whereNull('plan_id');
        $count = $query->count();

        if ($count === 0) {
            $this->info('No accounts with NULL plan_id. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} account(s) with NULL plan_id.");

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode — no changes applied.');

            return self::SUCCESS;
        }

        $updated = $query->update(['plan_id' => $freePlan->id]);

        $this->info("Updated {$updated} account(s) to the Free plan.");

        return self::SUCCESS;
    }
}
