<?php

namespace App\Helper;

class CommonHelper
{
    public static function allowMoreResources(int $maxExecutionTime = 60, string $memoryLimit = '512M'): void
    {
        ini_set('max_execution_time', $maxExecutionTime);
        ini_set('memory_limit', $memoryLimit);
    }

    public static function generateRandomCode(int $length, string $letters = 'ABCDEFGHJNPRSTWXYZ3456789'): string
    {
        $generator = fn (string $carry, int $item) => $carry . array_rand(array_flip(mb_str_split($letters)));

        return array_reduce(range(0, $length - 1), $generator, '');
    }
}
