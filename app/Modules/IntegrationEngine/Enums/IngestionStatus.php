<?php

namespace App\Modules\IntegrationEngine\Enums;

enum IngestionStatus: string
{
    case DISCOVERED = 'discovered';
    case CLAIMED = 'claimed';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case PARTIALLY_FAILED = 'partially_failed';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::PARTIALLY_FAILED, self::FAILED], true);
    }
}
