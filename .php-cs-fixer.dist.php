<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'cache', 'var', 'storage', 'node_modules'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                     => true,
        '@Symfony'                   => true,
        '@Symfony:risky'             => true,
        'strict_param'               => true,
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope'   => 'namespaced',
            'strict'  => true,
        ],
        'array_syntax'               => ['syntax' => 'short'],
        'no_unused_imports'          => true,
        'no_leading_import_slash'    => true,
        'ordered_imports'            => ['sort_algorithm' => 'alpha'],
    ])
    ->setFinder($finder)
    ->setUsingCache(true);
