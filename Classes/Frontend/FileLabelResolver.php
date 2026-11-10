<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use FourViewture\AiLabeling\Domain\Label;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * Label of a FAL file (original file of references / processed files).
 *
 * In the frontend the core overlays file metadata into the current language
 * (FileMetadataOverlayAspect, 12.4–14.3): tx_ailabeling_active/involvement are l10n_mode=exclude
 * (default language values), tx_ailabeling_label_text is translatable.
 */
final class FileLabelResolver
{
    /** @var array<string, Label|false> */
    private array $labels = [];

    public function __construct(
        private readonly LabelRenderer $labelRenderer,
        private readonly FrontendContext $context,
    ) {}

    public static function originalFile(FileInterface $file): ?File
    {
        if ($file instanceof FileReference || $file instanceof ProcessedFile) {
            $file = $file->getOriginalFile();
        }
        return $file instanceof File && $file->getUid() > 0 ? $file : null;
    }

    public function labelForFile(FileInterface $file, ?ServerRequestInterface $request = null): ?Label
    {
        $original = self::originalFile($file);
        if ($original === null) {
            return null;
        }
        $key = $original->getUid() . ':' . $this->context->getLanguageId($request);
        if (!isset($this->labels[$key])) {
            try {
                $metaData = $original->getMetaData()->get();
            } catch (\Throwable) {
                $metaData = [];
            }
            $this->labels[$key] = $this->labelRenderer->labelForRow($metaData, 'sys_file_metadata', self::fileType($original), $request) ?? false;
        }
        return $this->labels[$key] === false ? null : $this->labels[$key];
    }

    /**
     * File type as int (v14 may return the FileType enum instead of int).
     */
    public static function fileType(FileInterface $file): int
    {
        $type = $file instanceof File || $file instanceof FileReference ? $file->getType() : 0;
        return $type instanceof \BackedEnum ? (int)$type->value : (int)$type;
    }
}
