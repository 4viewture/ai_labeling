<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

/**
 * Frontend settings from TypoScript `plugin.tx_ailabeling.settings` (filled from the constants
 * `plugin.tx_ailabeling.*` or, with the site set, from site settings of the same name).
 *
 * Missing values fall back to the defaults below (= constant defaults), so CLI image processing
 * (e.g. cache warmers) produces the same processed files as a frontend request.
 */
final class LabelSettings
{
    public const POSITIONS = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];

    /**
     * Defaults. Placement: Code of Practice on Transparency of AI-Generated Content (10 June 2026),
     * Section 2, Sub-measure 1.2.2 a) – "e.g., in the top right corner of an image or video deep fake".
     * The Code does not fix numeric sizes (Measure 1.1 c: "different sizes depending on the context,
     * as long as … the disclosure is clear and distinguishable"), so size values are our defaults.
     */
    private const DEFAULTS = [
        'css.include' => true,
        'content.enable' => true,
        'label.text' => 'visible',
        'label.secondLayer' => false,
        'images.burnIn' => true,
        'images.position' => 'top-right',
        'images.sizePercent' => 8,
        'images.minSize' => 24,
        'images.maxSize' => 96,
        'images.altText' => true,
        'media.overlay' => true,
    ];

    /**
     * @param array<string, mixed> $values flat "dot" keys, see DEFAULTS
     */
    private function __construct(private readonly array $values) {}

    /**
     * @param array<string, mixed> $settings TypoScript array `plugin.tx_ailabeling.settings.` (nested "dot" arrays)
     */
    public static function fromTypoScript(array $settings): self
    {
        $values = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            [$group, $name] = explode('.', $key, 2);
            $value = $settings[$group . '.'][$name] ?? null;
            // missing, or a constant that is not defined at all ("{$plugin.tx_ailabeling…}" stays literally)
            if ($value === null || is_array($value) || str_starts_with((string)$value, '{$')) {
                continue;
            }
            if ((string)$value === '') {
                // TYPO3 13+ site settings: bool false is written to the constants as empty string
                if (is_bool(self::DEFAULTS[$key])) {
                    $values[$key] = false;
                }
                continue;
            }
            $values[$key] = $value;
        }
        return new self($values);
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public function includeCss(): bool
    {
        return $this->bool('css.include');
    }

    public function contentEnabled(): bool
    {
        return $this->bool('content.enable');
    }

    public function textVisible(): bool
    {
        return strtolower(trim((string)$this->values['label.text'])) !== 'hidden';
    }

    public function secondLayer(): bool
    {
        return $this->bool('label.secondLayer');
    }

    public function burnIn(): bool
    {
        return $this->bool('images.burnIn');
    }

    public function imagePosition(): string
    {
        $position = strtolower(trim((string)$this->values['images.position']));
        return in_array($position, self::POSITIONS, true) ? $position : self::DEFAULTS['images.position'];
    }

    public function imageSizePercent(): float
    {
        $value = (float)str_replace(',', '.', (string)$this->values['images.sizePercent']);
        return $value > 0 && $value <= 50 ? $value : (float)self::DEFAULTS['images.sizePercent'];
    }

    public function imageMinSize(): int
    {
        return max(8, (int)$this->values['images.minSize']);
    }

    public function imageMaxSize(): int
    {
        return max($this->imageMinSize(), (int)$this->values['images.maxSize']);
    }

    public function altText(): bool
    {
        return $this->bool('images.altText');
    }

    public function mediaOverlay(): bool
    {
        return $this->bool('media.overlay');
    }

    /**
     * Values that influence the burned-in result (part of the processed file identity, see README).
     *
     * @return array{position: string, sizePercent: float, minSize: int, maxSize: int}
     */
    public function burnInParameters(): array
    {
        return [
            'position' => $this->imagePosition(),
            'sizePercent' => $this->imageSizePercent(),
            'minSize' => $this->imageMinSize(),
            'maxSize' => $this->imageMaxSize(),
        ];
    }

    private function bool(string $key): bool
    {
        $value = $this->values[$key];
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}
