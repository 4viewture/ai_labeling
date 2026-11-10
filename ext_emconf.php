<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AI Labeling (EU AI Act Art. 50)',
    'description' => 'Transparency fields for AI-generated or AI-modified files and content elements, editor guide and automatic detection from file metadata.',
    'category' => 'be',
    'state' => 'beta',
    'author' => '4Viewture GmbH',
    'author_company' => '4Viewture GmbH',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.99.99',
            'typo3' => '12.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
