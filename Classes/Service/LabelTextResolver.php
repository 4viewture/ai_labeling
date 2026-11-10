<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Domain\Labeling;

/**
 * Determines the label text: the editor's own text or a default text based on the guide's examples.
 * Intended to be reused by the frontend rendering (ViewHelper / DataProcessor) later.
 */
final class LabelTextResolver
{
    /**
     * @param array<string, mixed> $row
     * @param int|string|null $recordType sys_file_metadata: file type (sys_file.type), tt_content: CType
     */
    public function resolve(array $row, string $table, int|string|null $recordType = null): string
    {
        $custom = trim((string)($row[Labeling::F_LABEL_TEXT] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $involvement = $row[Labeling::F_INVOLVEMENT] ?? 0;
        $involvement = (int)(is_array($involvement) ? reset($involvement) : $involvement);
        $key = $this->defaultLabelKey($table, $recordType, $involvement === Labeling::INVOLVEMENT_MODIFIED ? 'modified' : 'generated');
        $lang = $GLOBALS['LANG'] ?? null;
        return $lang !== null ? (string)$lang->sL($key) : $key;
    }

    /**
     * @param 'generated'|'modified' $labelType
     */
    public function defaultLabelKey(string $table, int|string|null $recordType, string $labelType): string
    {
        $medium = 'text';
        if ($table === 'sys_file_metadata') {
            $medium = match ((int)$recordType) {
                2, 4 => 'visual',
                3 => 'audio',
                default => 'text',
            };
        }
        return Labeling::LLL_GUIDE . 'default.' . $medium . '.' . $labelType;
    }
}
