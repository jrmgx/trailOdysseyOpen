<?php

$ruleset = new TwigCsFixer\Ruleset\Ruleset();
$ruleset->addStandard(new TwigCsFixer\Standard\Generic());
$ruleset->removeSniff(TwigCsFixer\Sniff\EmptyLinesSniff::class);

$config = new TwigCsFixer\Config\Config();
$config->setRuleset($ruleset);

return $config;
