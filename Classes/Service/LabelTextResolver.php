<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Domain\Label;
use FourViewture\AiLabeling\Domain\Labeling;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Determines the label text: the editor's own text or a default text based on the guide's examples.
 *
 * Backend (FormEngine) uses $GLOBALS['LANG']; the frontend passes a LanguageService of the
 * site language of the current request (see FrontendContext::getLanguageService()).
 */
final class LabelTextResolver
{
    /**
     * @param array<string, mixed> $row
     * @param int|string|null $recordType sys_file_metadata: file type (sys_file.type), tt_content: CType
     * @param LanguageService|null $languageService null = $GLOBALS['LANG'] (backend)
     */
    public function resolve(array $row, string $table, int|string|null $recordType = null, ?LanguageService $languageService = null): string
    {
        $custom = trim((string)($row[Labeling::F_LABEL_TEXT] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        return $this->defaultText($table, $recordType, Label::typeFromRow($row), $languageService);
    }

    /**
     * @param Label::GENERATED|Label::MODIFIED $labelType
     */
    public function defaultText(string $table, int|string|null $recordType, string $labelType, ?LanguageService $languageService = null): string
    {
        $key = $this->defaultLabelKey($table, $recordType, $labelType);
        $lang = $languageService ?? ($GLOBALS['LANG'] ?? null);
        $text = $lang instanceof LanguageService ? (string)$lang->sL($key) : '';
        return $text !== '' ? $text : $key;
    }

    /**
     * @param 'generated'|'modified' $labelType
     */
    public function defaultLabelKey(string $table, int|string|null $recordType, string $labelType): string
    {
        $medium = 'text';
        if ($table === 'sys_file_metadata' || $table === 'sys_file') {
            $medium = self::mediumForFileType($recordType);
        }
        return Labeling::LLL_GUIDE . 'default.' . $medium . '.' . $labelType;
    }

    /**
     * FAL file types as integers (no AbstractFile::FILETYPE_* constants, removed/enum in v13+):
     * 2 image, 3 audio, 4 video.
     *
     * @return 'text'|'visual'|'audio'
     */
    public static function mediumForFileType(int|string|null $fileType): string
    {
        return match ((int)$fileType) {
            2, 4 => 'visual',
            3 => 'audio',
            default => 'text',
        };
    }
}
