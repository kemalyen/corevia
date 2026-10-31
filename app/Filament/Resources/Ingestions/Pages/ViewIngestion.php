<?php

namespace App\Filament\Resources\Ingestions\Pages;

use App\Filament\Resources\Ingestions\IngestionResource;
use App\Modules\IntegrationEngine\Actions\RecoverIngestion;
use App\Modules\IntegrationEngine\Models\Ingestion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIngestion extends ViewRecord
{
    protected static string $resource = IngestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Retry Integration')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->authorize('retry')
                ->requiresConfirmation()
                ->modalDescription('The quarantined file will be restored and queued for processing again. Previous row errors remain visible until processing starts.')
                ->action(function (Ingestion $record, RecoverIngestion $recoverIngestion): void {
                    $recovered = $recoverIngestion->handle($record->getKey(), retryFailed: true);

                    if ($recovered === 1) {
                        Notification::make()
                            ->title('Integration queued for retry')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Integration could not be retried')
                        ->body($record->fresh()->last_error ?? 'The integration is no longer eligible for retry.')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
