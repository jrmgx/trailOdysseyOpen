<?php

namespace App\Message;

use App\MessageHandler\ImportGpxMessageHandler;
use App\Service\GpxService;

/**
 * @see ImportGpxMessageHandler
 */
readonly class ImportGpxMessage
{
    /**
     * @param array<int, string> $filePaths
     */
    public function __construct(
        public int $tripId,
        public array $filePaths,
        public int $importVariant = GpxService::IMPORT_BASE,
    ) {
    }
}
