<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StimulusExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('stimulus_js_load_start', $this->start(...), ['is_safe' => ['all']]),
            new TwigFunction('stimulus_js_load_end', $this->end(...), ['is_safe' => ['all']]),
        ];
    }

    public function start(string $controllerName, bool $firstLoad = false): string
    {
        return $firstLoad ?
            'onLoad(function () {' :
            "if (typeof $controllerName !== 'undefined') {"
        ;
    }

    public function end(bool $firstLoad = false): string
    {
        return $firstLoad ? '});' : '}';
    }
}
