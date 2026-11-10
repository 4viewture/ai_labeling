<?php

declare(strict_types=1);

defined('TYPO3') or die();

// FormEngine node rendering the editor guide incl. live assessment on top of the "AI" tab
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1762790401] = [
    'nodeName' => 'aiLabelingGuide',
    'priority' => 40,
    'class' => \FourViewture\AiLabeling\Form\Element\GuideInformation::class,
];

// Recalculate status / active flag whenever records are saved through DataHandler
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['ai_labeling']
    = \FourViewture\AiLabeling\Hooks\DataHandlerHook::class;

// TYPO3 12 only: AI overlay on record icons (replaced by ModifyRecordOverlayIconIdentifierEvent in 13+,
// see Configuration/Services.yaml – the hook is simply not called anymore in 13+)
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Core\Imaging\IconFactory::class]['overrideIconOverlay']['ai_labeling']
    = \FourViewture\AiLabeling\Hooks\IconOverlayHook::class;

// Global Fluid namespace "ai" (http://typo3.org/ns/FourViewture/AiLabeling/ViewHelpers also works as xmlns)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ai'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ai'][] = 'FourViewture\\AiLabeling\\ViewHelpers';

// Video / audio / online media: decorate the renderer of labelled files (12.4–14.3 register renderers here)
\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::class)
    ->registerRendererClass(\FourViewture\AiLabeling\Resource\Rendering\AiLabelMediaRenderer::class);
