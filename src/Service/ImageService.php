<?php

namespace App\Service;

class ImageService
{
    public static function convertHeicToJpg(string $inputFile, string $outputFile): void
    {
        $imagick = new \Imagick();
        $imagick->readImage($inputFile);
        $imagick->setImageFormat('jpeg');
        $imagick->writeImage($outputFile);
        $imagick->clear();
        $imagick->destroy();
    }
}
