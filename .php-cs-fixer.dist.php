<?php declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath([
        'Fixtures/',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
        'strict_types' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_line_after_imports' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
