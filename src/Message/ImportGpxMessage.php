<?php

namespace App\Message;

use App\MessageHandler\ImportGpxMessageHandler;

/**
 * @see ImportGpxMessageHandler
 */
class ImportGpxMessage
{
    /**
     * @param array<int, string> $filePaths
     */
    public function __construct(
        public readonly int $tripId,
        public readonly array $filePaths,
    ) {
    }
}
