<?php

declare(strict_types=1);

return [
    'dependencies' => ['backend', 'core'],
    // TYPO3 12.4/13.4: the backend import map contains only modules that are used on the page; modules
    // loaded by the context menu (data-callback-module) must be tagged, otherwise import() fails.
    // TYPO3 14 ignores the tag for that purpose (all backend imports are available) – harmless there.
    'tags' => ['backend.contextmenu'],
    'imports' => [
        '@4viewture/ai-labeling/' => 'EXT:ai_labeling/Resources/Public/JavaScript/',
    ],
];
