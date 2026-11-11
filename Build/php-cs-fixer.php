<?php

declare(strict_types=1);

// Coding guidelines: PER-CS 2.0 plus a few TYPO3 conventions. composer ci:php:cs / composer fix:php:cs
$root = dirname(__DIR__);
$finder = (new PhpCsFixer\Finder())
    ->in([$root . '/Classes', $root . '/Configuration', $root . '/Tests', $root . '/Build'])
    ->append([$root . '/ext_localconf.php'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(dirname(__DIR__) . '/.Build/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'no_empty_phpdoc' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_trim' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'cast_spaces' => ['space' => 'none'],
        'concat_space' => ['spacing' => 'one'],
        'native_function_invocation' => false,
    ]);
