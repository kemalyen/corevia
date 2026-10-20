<?php

namespace App\Console\Commands;

use App\Modules\IntegrationEngine\Actions\RecoverIngestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integration:recover {--id= : Recover one ingestion immediately} {--retry-failed : Deliberately retry failed or partially failed ingestions}')]
#[Description('Requeue stranded ingestion claims or explicitly retry quarantined files')]
class RecoverIntegrationIngestions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecoverIngestion $recoverIngestion): int
    {
        $id = $this->option('id');
        $count = $recoverIngestion->handle(
            $id === null ? null : (int) $id,
            (bool) $this->option('retry-failed'),
        );
        $this->info("Recovered {$count} integration ingestion(s).");

        return self::SUCCESS;
    }
}
