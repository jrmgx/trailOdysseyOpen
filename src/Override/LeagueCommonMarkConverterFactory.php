<?php

namespace App\Override;

use App\CommonMark\PhotoExtension;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * Override to bypass https://github.com/twigphp/Twig/issues/3725.
 */
final class LeagueCommonMarkConverterFactory
{
    public function __construct(
        private readonly PhotoExtension $photoExtension,
        private readonly string $projectHost,
    ) {
    }

    public function __invoke(): CommonMarkConverter
    {
        $config = [
            'external_link' => [
                'internal_hosts' => $this->projectHost,
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
            'default_attributes' => [
                Image::class => [
                    'loading' => 'lazy',
                ],
                Table::class => [
                    'class' => 'table table-bordered table-striped',
                ],
            ],
        ];

        $converter = new CommonMarkConverter($config);
        $converter->getEnvironment()->addExtension(new DefaultAttributesExtension());
        $converter->getEnvironment()->addExtension(new ExternalLinkExtension());
        $converter->getEnvironment()->addExtension(new AutolinkExtension());
        $converter->getEnvironment()->addExtension(new TableExtension());
        $converter->getEnvironment()->addExtension($this->photoExtension);

        return $converter;
    }
}
