<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Domain;

/**
 * Constants shared by TCA, DataHandler hook, detector and frontend (later).
 */
final class Labeling
{
    public const EXT_KEY = 'ai_labeling';
    public const LLL = 'LLL:EXT:ai_labeling/Resources/Private/Language/locallang_db.xlf:';
    public const LLL_GUIDE = 'LLL:EXT:ai_labeling/Resources/Private/Language/locallang_guide.xlf:';

    // Involvement of AI
    public const INVOLVEMENT_NONE = 0;
    public const INVOLVEMENT_ASSISTED = 1;   // technical adjustments only (colour correction, spell check …)
    public const INVOLVEMENT_MODIFIED = 2;   // EU icon "AI MODIFIED"
    public const INVOLVEMENT_GENERATED = 3;  // EU icon "AI GENERATED"

    // Label mode
    public const MODE_AUTO = 0;
    public const MODE_FORCE = 1;
    public const MODE_SUPPRESS = 2;

    // Computed status
    public const STATUS_NONE = 0;
    public const STATUS_OPTIONAL = 1;
    public const STATUS_RECOMMENDED = 2;
    public const STATUS_REQUIRED = 3;

    // Field names
    public const F_INVOLVEMENT = 'tx_ailabeling_involvement';
    public const F_TOOL = 'tx_ailabeling_tool';
    public const F_REAL_REFERENCE = 'tx_ailabeling_real_reference';
    public const F_DECEPTIVE = 'tx_ailabeling_deceptive';
    public const F_SYNTHETIC_PERSON = 'tx_ailabeling_synthetic_person';
    public const F_PRODUCT_ALTERED = 'tx_ailabeling_product_altered';
    public const F_PUBLIC_INTEREST = 'tx_ailabeling_public_interest';
    public const F_HUMAN_REVIEW = 'tx_ailabeling_human_review';
    public const F_REVIEWER = 'tx_ailabeling_reviewer';
    public const F_MODE = 'tx_ailabeling_mode';
    public const F_LABEL_TEXT = 'tx_ailabeling_label_text';
    public const F_NOTES = 'tx_ailabeling_notes';
    public const F_DETECTED = 'tx_ailabeling_detected';
    public const F_DETECTION = 'tx_ailabeling_detection';
    public const F_STATUS = 'tx_ailabeling_status';
    public const F_REASONS = 'tx_ailabeling_reasons';
    public const F_ACTIVE = 'tx_ailabeling_active';

    /** Fields that influence the assessment (used by hook + JS) */
    public const DECISION_FIELDS = [
        self::F_INVOLVEMENT,
        self::F_MODE,
        self::F_REAL_REFERENCE,
        self::F_DECEPTIVE,
        self::F_SYNTHETIC_PERSON,
        self::F_PRODUCT_ALTERED,
        self::F_PUBLIC_INTEREST,
        self::F_HUMAN_REVIEW,
    ];
}
