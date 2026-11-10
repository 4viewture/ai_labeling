<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Domain\Labeling;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ResourceInterface;

/**
 * Reads the persisted AI labeling state of a FAL resource (default language metadata).
 */
final class FileLabelState
{
    /** @var array<int, array<string, int>> */
    private array $cache = [];

    /**
     * @return array{involvement: int, status: int, active: bool}|null null if the resource is no file or not AI-related
     */
    public function forResource(ResourceInterface $resource): ?array
    {
        if ($resource instanceof FileReference) {
            $resource = $resource->getOriginalFile();
        }
        if (!$resource instanceof File || $resource->getUid() <= 0) {
            return null;
        }
        $uid = $resource->getUid();
        if (!isset($this->cache[$uid])) {
            try {
                $metaData = $resource->getMetaData()->get();
            } catch (\Throwable) {
                $metaData = [];
            }
            $this->cache[$uid] = [
                'involvement' => (int)($metaData[Labeling::F_INVOLVEMENT] ?? 0),
                'status' => (int)($metaData[Labeling::F_STATUS] ?? 0),
                'active' => (int)($metaData[Labeling::F_ACTIVE] ?? 0),
            ];
        }
        $state = $this->cache[$uid];
        if ($state['active'] !== 1 && $state['involvement'] < Labeling::INVOLVEMENT_MODIFIED) {
            return null;
        }
        return [
            'involvement' => $state['involvement'],
            'status' => $state['status'],
            'active' => $state['active'] === 1,
        ];
    }
}
