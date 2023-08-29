<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Migration Toolkit',
    'description' => 'Toolset for supporting Typo3 migrations by rewriting templates, folders, files using commands or migration wizards.',
    'category' => 'be',
    'author' => 'Martin Keller',
    'author_email' => 'martin.keller@taketool.de',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
