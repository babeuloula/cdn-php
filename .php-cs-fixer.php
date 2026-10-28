<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.cache/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x2Migration' => true,
        '@PHP8x3Migration' => true,
        '@PHP8x4Migration' => true,
        '@PHP8x5Migration' => true,
        // Avoiding that adding a property changes the alignment of all properties
        'phpdoc_align' => ['align' => 'left'],
        'declare_strict_types' => true,
        // Custom rules, here for historical reasons
        'yoda_style' => ['equal' => true, 'identical' => true, 'less_and_greater' => null],
        // Disabled: mb_strlen() on binary payloads would break byte-size checks (e.g. HttpFetcher)
        'mb_str_functions' => false,
        'concat_space' => ['spacing' => 'one'],
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'get_class_to_class_keyword' => true,
        'void_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'fully_qualified_strict_types' => true,
        'phpdoc_to_comment' => [
            'ignored_tags' => ['var', 'param'],
        ],
        'php_unit_attributes' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'php_unit_test_case_static_method_calls' => ['call_type' => 'static'],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'method_public_abstract',
                'method_protected_abstract',
                'method_private_abstract',
                'construct',
                'destruct',
                'method:__invoke',
                'magic',
                'phpunit',
                'method_public_static',
                'method_protected_static',
                'method_private_static',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
    ])
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
