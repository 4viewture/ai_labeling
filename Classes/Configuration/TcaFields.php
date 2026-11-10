<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Configuration;

use FourViewture\AiLabeling\Domain\Labeling as L;

/**
 * Shared TCA definition for sys_file_metadata and tt_content.
 * Only uses TCA features available in TYPO3 12.4 – 14.
 */
final class TcaFields
{
    private const SHOWN_IF_AI = 'FIELD:' . L::F_INVOLVEMENT . ':>:' . L::INVOLVEMENT_ASSISTED;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function columns(): array
    {
        $lll = L::LLL;

        return [
            L::F_INVOLVEMENT => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'involvement',
                'description' => $lll . 'involvement.description',
                'onChange' => 'reload',
                'config' => [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'default' => L::INVOLVEMENT_NONE,
                    'items' => [
                        ['label' => $lll . 'involvement.0', 'value' => L::INVOLVEMENT_NONE],
                        ['label' => $lll . 'involvement.1', 'value' => L::INVOLVEMENT_ASSISTED],
                        ['label' => $lll . 'involvement.2', 'value' => L::INVOLVEMENT_MODIFIED],
                        ['label' => $lll . 'involvement.3', 'value' => L::INVOLVEMENT_GENERATED],
                    ],
                    // fieldWizard (not fieldInformation): fieldInformation only allows
                    // <a><br><div><em><i><p><strong><span><code>, the guide needs lists, details etc.
                    'fieldWizard' => [
                        'aiLabelingGuide' => [
                            'renderType' => 'aiLabelingGuide',
                            'after' => ['selectIcons'],
                        ],
                    ],
                ],
            ],
            L::F_TOOL => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'tool',
                'description' => $lll . 'tool.description',
                'displayCond' => 'FIELD:' . L::F_INVOLVEMENT . ':>:' . L::INVOLVEMENT_NONE,
                'config' => [
                    'type' => 'input',
                    'size' => 40,
                    'max' => 255,
                    'eval' => 'trim',
                ],
            ],
            L::F_REAL_REFERENCE => self::check('real_reference'),
            L::F_DECEPTIVE => self::check('deceptive'),
            L::F_SYNTHETIC_PERSON => self::check('synthetic_person'),
            L::F_PRODUCT_ALTERED => self::check('product_altered'),
            L::F_PUBLIC_INTEREST => self::check('public_interest'),
            L::F_HUMAN_REVIEW => self::check('human_review'),
            L::F_REVIEWER => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'reviewer',
                'description' => $lll . 'reviewer.description',
                'displayCond' => self::SHOWN_IF_AI,
                'config' => [
                    'type' => 'input',
                    'size' => 30,
                    'max' => 255,
                    'eval' => 'trim',
                ],
            ],
            L::F_MODE => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'mode',
                'description' => $lll . 'mode.description',
                'config' => [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'default' => L::MODE_AUTO,
                    'items' => [
                        ['label' => $lll . 'mode.0', 'value' => L::MODE_AUTO],
                        ['label' => $lll . 'mode.1', 'value' => L::MODE_FORCE],
                        ['label' => $lll . 'mode.2', 'value' => L::MODE_SUPPRESS],
                    ],
                ],
            ],
            L::F_LABEL_TEXT => [
                'exclude' => true,
                'label' => $lll . 'label_text',
                'description' => $lll . 'label_text.description',
                'config' => [
                    'type' => 'input',
                    'size' => 50,
                    'max' => 255,
                    'eval' => 'trim',
                    'placeholder' => $lll . 'label_text.placeholder',
                ],
            ],
            L::F_NOTES => [
                'exclude' => true,
                'label' => $lll . 'notes',
                'config' => [
                    'type' => 'text',
                    'rows' => 3,
                    'cols' => 50,
                ],
            ],
            L::F_DETECTED => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'detected',
                'displayCond' => 'FIELD:' . L::F_DETECTED . ':=:1',
                'config' => [
                    'type' => 'check',
                    'renderType' => 'checkboxToggle',
                    'readOnly' => true,
                    'items' => [['label' => '']],
                ],
            ],
            L::F_DETECTION => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'detection',
                'displayCond' => 'FIELD:' . L::F_DETECTED . ':=:1',
                'config' => [
                    'type' => 'text',
                    'rows' => 3,
                    'readOnly' => true,
                ],
            ],
            // Computed by DataHandlerHook / AutoLabeler. Not part of the form (the guide shows the
            // assessment), but defined as readOnly select/check so they can be shown as human-readable
            // columns via the column selector of the file list (and the list module for tt_content).
            L::F_STATUS => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'status',
                'config' => [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'readOnly' => true,
                    'default' => L::STATUS_NONE,
                    'items' => [
                        ['label' => $lll . 'status.0', 'value' => L::STATUS_NONE],
                        ['label' => $lll . 'status.1', 'value' => L::STATUS_OPTIONAL],
                        ['label' => $lll . 'status.2', 'value' => L::STATUS_RECOMMENDED],
                        ['label' => $lll . 'status.3', 'value' => L::STATUS_REQUIRED],
                    ],
                ],
            ],
            L::F_ACTIVE => [
                'exclude' => true,
                'l10n_mode' => 'exclude',
                'label' => $lll . 'active',
                'config' => [
                    // no items: list views render "Yes"/"No"
                    'type' => 'check',
                    'readOnly' => true,
                    'default' => 0,
                ],
            ],
            L::F_REASONS => [
                'l10n_mode' => 'exclude',
                'label' => $lll . 'reasons',
                'config' => ['type' => 'passthrough'],
            ],
        ];
    }

    /**
     * Column definitions for CREATE TABLE (used by ext_tables.sql equivalent for all labeled tables).
     */
    public static function sqlColumns(): string
    {
        return implode(",\n", [
            L::F_INVOLVEMENT . " int(11) unsigned DEFAULT '0' NOT NULL",
            L::F_TOOL . " varchar(255) DEFAULT '' NOT NULL",
            L::F_REAL_REFERENCE . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_DECEPTIVE . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_SYNTHETIC_PERSON . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_PRODUCT_ALTERED . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_PUBLIC_INTEREST . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_HUMAN_REVIEW . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_REVIEWER . " varchar(255) DEFAULT '' NOT NULL",
            L::F_MODE . " int(11) unsigned DEFAULT '0' NOT NULL",
            L::F_LABEL_TEXT . " varchar(255) DEFAULT '' NOT NULL",
            L::F_NOTES . " text",
            L::F_DETECTED . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
            L::F_DETECTION . " text",
            L::F_STATUS . " int(11) unsigned DEFAULT '0' NOT NULL",
            L::F_REASONS . " varchar(255) DEFAULT '' NOT NULL",
            L::F_ACTIVE . " tinyint(1) unsigned DEFAULT '0' NOT NULL",
        ]);
    }

    /**
     * @return array<string, array{showitem: string, label: string}>
     */
    public static function palettes(): array
    {
        $lll = L::LLL;
        return [
            'ailabeling_involvement' => [
                'label' => $lll . 'palette.involvement',
                'showitem' => L::F_INVOLVEMENT . ', --linebreak--, ' . L::F_TOOL,
            ],
            'ailabeling_media' => [
                'label' => $lll . 'palette.media',
                'showitem' => L::F_REAL_REFERENCE . ', ' . L::F_DECEPTIVE . ', --linebreak--, '
                    . L::F_SYNTHETIC_PERSON . ', ' . L::F_PRODUCT_ALTERED,
            ],
            'ailabeling_text' => [
                'label' => $lll . 'palette.text',
                'showitem' => L::F_PUBLIC_INTEREST . ', ' . L::F_HUMAN_REVIEW . ', --linebreak--, ' . L::F_REVIEWER,
            ],
            'ailabeling_label' => [
                'label' => $lll . 'palette.label',
                'showitem' => L::F_MODE . ', --linebreak--, ' . L::F_LABEL_TEXT . ', --linebreak--, ' . L::F_NOTES,
            ],
            'ailabeling_detection' => [
                'label' => $lll . 'palette.detection',
                'showitem' => L::F_DETECTED . ', --linebreak--, ' . L::F_DETECTION,
            ],
        ];
    }

    /**
     * Showitem string for the "AI" tab.
     *
     * @param bool $withMedia include deepfake / product questions
     * @param bool $withText include public-interest text questions
     */
    public static function tab(bool $withMedia = true, bool $withText = true): string
    {
        $items = ['--div--;' . L::LLL . 'tab', '--palette--;;ailabeling_involvement'];
        if ($withMedia) {
            $items[] = '--palette--;;ailabeling_media';
        }
        if ($withText) {
            $items[] = '--palette--;;ailabeling_text';
        }
        $items[] = '--palette--;;ailabeling_label';
        $items[] = '--palette--;;ailabeling_detection';
        return implode(', ', $items);
    }

    /**
     * @return array<string, mixed>
     */
    private static function check(string $name): array
    {
        return [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => L::LLL . $name,
            'description' => L::LLL . $name . '.description',
            'displayCond' => self::SHOWN_IF_AI,
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
                'items' => [['label' => '']],
            ],
        ];
    }
}
