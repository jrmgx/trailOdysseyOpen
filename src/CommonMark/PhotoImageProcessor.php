<?php

declare(strict_types=1);

namespace App\CommonMark;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PhotoImageProcessor
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(DocumentParsedEvent $e): void
    {
        foreach ($e->getDocument()->iterator() as $image) {
            if (!($image instanceof Image)) {
                continue;
            }

            if (!str_starts_with($image->getUrl(), '/')) {
                continue;
            }

            if (3 !== mb_substr_count($image->getUrl(), '/')) {
                continue;
            }

            [$user, $trip, $path] = explode('/', trim($image->getUrl(), '/'));

            $image->setUrl($this->urlGenerator->generate(
                'public_photo', compact('user', 'trip', 'path')
            ));
        }
    }
}
