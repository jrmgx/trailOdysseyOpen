<?php

namespace App\Helper;

class CommonHelper
{
    public static function allowMoreResources(int $maxExecutionTime = 60, string $memoryLimit = '512M'): void
    {
        ini_set('max_execution_time', $maxExecutionTime);
        ini_set('memory_limit', $memoryLimit);
    }
}
