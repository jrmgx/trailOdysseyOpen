<?php

namespace App\CommonMark;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('twig.markdown.league_extension')]
class PhotoExtension implements ExtensionInterface
{
    public function __construct(
        private readonly PhotoImageProcessor $photoImageProcessor,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(
            DocumentParsedEvent::class,
            $this->photoImageProcessor,
            -50
        );
    }
}
