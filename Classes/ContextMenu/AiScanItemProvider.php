<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\ContextMenu;

use FourViewture\AiLabeling\Controller\FileScanController;
use FourViewture\AiLabeling\Service\AutoLabeler;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * File list context menu: "Scan for AI content" for files (after "Edit metadata").
 *
 * Registered by autoconfiguration (ProviderInterface -> tag backend.contextmenu.itemprovider,
 * 12.4–14.3). Priority 90: runs after the core FileProvider (100), so its items can be extended.
 * The action is handled by Resources/Public/JavaScript/context-menu-scan.js
 * (callback contract (table, uid, dataset) identical in 12.4–14.3).
 */
final class AiScanItemProvider extends AbstractProvider
{
    /** @var array<string, array<string, string>> */
    protected $itemsConfiguration = [
        'aiLabelingScan' => [
            'type' => 'item',
            'label' => 'LLL:EXT:ai_labeling/Resources/Private/Language/locallang_be.xlf:contextMenu.scan',
            'iconIdentifier' => 'ai-labeling',
            'callbackAction' => 'scanFile',
        ],
    ];

    private ?File $file = null;

    public function __construct(private readonly AutoLabeler $autoLabeler)
    {
        parent::__construct();
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function canHandle(): bool
    {
        return $this->table === 'sys_file';
    }

    protected function initialize()
    {
        parent::initialize();
        try {
            $object = GeneralUtility::makeInstance(ResourceFactory::class)->retrieveFileOrFolderObject($this->identifier);
        } catch (\Throwable) {
            $object = null;
        }
        $this->file = $object instanceof File ? $object : null;
    }

    /**
     * @param array<string, mixed> $items
     * @return array<string, mixed>
     */
    public function addItems(array $items): array
    {
        $this->initialize();
        $own = $this->prepareItems($this->itemsConfiguration);
        if ($own === []) {
            return $items;
        }
        $position = array_search('editMetadata', array_keys($items), true);
        if ($position === false) {
            return $items + $own;
        }
        return array_slice($items, 0, $position + 1, true) + $own + array_slice($items, $position + 1, null, true);
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true) || $this->file === null) {
            return false;
        }
        return $this->autoLabeler->supports($this->file) && FileScanController::canEditMetadata($this->file, $this->backendUser);
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        return ['data-callback-module' => '@4viewture/ai-labeling/context-menu-scan'];
    }
}
