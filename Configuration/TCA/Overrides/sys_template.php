<?php

declare(strict_types=1);

defined('TYPO3') or die();

// TYPO3 12.4 – 14: static TypoScript template. TYPO3 13+ sites can use the site set
// "4viewture/ai-labeling" (Configuration/Sets/AiLabeling) instead – do not include both.
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'ai_labeling',
    'Configuration/TypoScript/',
    'AI Labeling: frontend output'
);
