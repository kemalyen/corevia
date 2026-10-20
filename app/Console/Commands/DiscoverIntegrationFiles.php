<?php

namespace App\Console\Commands;

use App\Modules\IntegrationEngine\Actions\DiscoverReadyFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integration:discover')]
#[Description('Discover, claim, and queue ready integration CSV files')]
class DiscoverIntegrationFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscoverReadyFiles $discoverReadyFiles): int
    {
        $count = $discoverReadyFiles->handle();
        $this->info("Queued {$count} integration file(s).");

        return self::SUCCESS;
    }
}
