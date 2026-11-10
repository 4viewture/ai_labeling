<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    // Overlays for file icons (see \FourViewture\AiLabeling\EventListener\AiFileIconOverlay)
    'overlay-ai-labeled' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_labeling/Resources/Public/Icons/overlay-ai-labeled.svg',
    ],
    'overlay-ai-unlabeled' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_labeling/Resources/Public/Icons/overlay-ai-unlabeled.svg',
    ],
    'ai-labeling' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ai_labeling/Resources/Public/Icons/Extension.svg',
    ],
];
