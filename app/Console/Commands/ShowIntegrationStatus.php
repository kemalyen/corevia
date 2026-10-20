<?php

namespace App\Console\Commands;

use App\Modules\IntegrationEngine\Models\Ingestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integration:status {--id= : Show one ingestion} {--errors : Include recorded row errors}')]
#[Description('Inspect ingestion lifecycle status, counts, and errors')]
class ShowIntegrationStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->option('id');
        $ingestions = Ingestion::query()
            ->when($id !== null, fn ($query) => $query->whereKey((int) $id))
            ->latest('id')
            ->limit($id === null ? 20 : 1)
            ->get();

        $this->table(
            ['ID', 'Source', 'Dataset', 'Status', 'Rows', 'Valid', 'Failed', 'Created', 'Updated', 'Filename'],
            $ingestions->map(fn (Ingestion $ingestion): array => [
                $ingestion->id,
                $ingestion->source,
                $ingestion->dataset->value,
                $ingestion->status->value,
                $ingestion->total_rows,
                $ingestion->valid_rows,
                $ingestion->failed_rows,
                $ingestion->created_records,
                $ingestion->updated_records,
                $ingestion->original_filename,
            ])->all(),
        );

        if ((bool) $this->option('errors')) {
            foreach ($ingestions as $ingestion) {
                $this->newLine();
                $this->line("Errors for ingestion {$ingestion->id}");
                $this->table(
                    ['Row', 'Field', 'Code', 'Message'],
                    $ingestion->errors()->orderBy('row_number')->orderBy('id')->get()
                        ->map(fn ($error): array => [
                            $error->row_number,
                            $error->field,
                            $error->code,
                            $error->message,
                        ])->all(),
                );
            }
        }

        return self::SUCCESS;
    }
}
