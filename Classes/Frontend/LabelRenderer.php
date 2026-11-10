<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use FourViewture\AiLabeling\Domain\Label;
use FourViewture\AiLabeling\Service\LabelTextResolver;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the shared label component (real DOM text, inline SVG icon – no CSS generated content, WCAG F87):
 *
 *   <span class="ai-label ai-label--generated" data-ai-label="generated">
 *     <svg class="ai-label__icon" aria-hidden="true" focusable="false">…</svg>
 *     <span class="ai-label__text">KI-generiert</span>
 *   </span>
 *
 * The icon is the compact EU "AI" symbol (Resources/Public/Icons/EU/ai-symbol-black.svg, solid variant).
 * Its paths are merged into one path with fill-rule="evenodd" so the letters become cut-outs: the
 * symbol is drawn in currentColor only and the pill background shows through the letters. That way it
 * keeps working in forced-colors mode (a fixed white letter fill would vanish on a white CanvasText disc).
 */
final class LabelRenderer
{
    private const SYMBOL_FILE = 'Resources/Public/Icons/EU/ai-symbol-black.svg';

    /**
     * Bounding box of the disc in the official SVG (viewBox 0 0 566.93 566.93):
     * centre 272.03/283.47, radius 182.75 -> x 89.28, y 100.72, size 365.5 (crops the built-in margin).
     */
    private const SYMBOL_VIEWBOX = '89.28 100.72 365.5 365.5';

    private const MODIFIERS = ['overlay', 'media', 'content', 'caption', 'inline'];

    private static ?string $symbolPath = null;

    public function __construct(
        private readonly FrontendContext $context,
        private readonly LabelTextResolver $textResolver,
    ) {}

    /**
     * Resolves the label of a record row (or null if tx_ailabeling_active != 1).
     *
     * @param array<string, mixed> $row record incl. tx_ailabeling_* fields (language overlaid)
     * @param int|string|null $recordType sys_file_metadata/sys_file: file type
     */
    public function labelForRow(array $row, string $table, int|string|null $recordType = null, ?ServerRequestInterface $request = null): ?Label
    {
        if (!Label::isActive($row)) {
            return null;
        }
        return new Label(
            Label::typeFromRow($row),
            $this->textResolver->resolve($row, $table, $recordType, $this->context->getLanguageService($request))
        );
    }

    /**
     * @param array{
     *     modifiers?: list<string>,
     *     textVisible?: bool|null,
     *     secondLayer?: bool|null,
     *     request?: ServerRequestInterface|null,
     * } $options
     */
    public function render(Label $label, array $options = []): string
    {
        $request = $options['request'] ?? null;
        $settings = $this->context->getSettings($request);
        $textVisible = $options['textVisible'] ?? $settings->textVisible();
        $modifiers = array_values(array_intersect($options['modifiers'] ?? [], self::MODIFIERS));
        // Overlays use pointer-events: none – an interactive second layer would not be operable there
        $secondLayer = ($options['secondLayer'] ?? $settings->secondLayer()) && !in_array('overlay', $modifiers, true);

        $classes = ['ai-label', 'ai-label--' . $label->type];
        foreach ($modifiers as $modifier) {
            $classes[] = 'ai-label--' . $modifier;
        }
        if (!$textVisible) {
            $classes[] = 'ai-label--text-hidden';
        }

        $html = '<span class="' . implode(' ', $classes) . '" data-ai-label="' . $label->type . '">'
            . $this->renderIcon()
            . '<span class="ai-label__text' . ($textVisible ? '' : ' ai-label__text--sr-only') . '">'
            . self::escape($label->text) . '</span>';

        if ($secondLayer) {
            $html .= $this->renderSecondLayer($label, $request);
        }
        return $html . '</span>';
    }

    public function renderIcon(): string
    {
        return '<svg class="ai-label__icon" viewBox="' . self::SYMBOL_VIEWBOX . '" fill="currentColor"'
            . ' aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
            . '<path fill-rule="evenodd" d="' . self::symbolPath() . '"/></svg>';
    }

    /**
     * Optional second layer: disclosure button + native popover (top layer, out of flow – does not
     * affect the layout). aria-expanded is kept in sync by Resources/Public/JavaScript/ai-label.js.
     */
    private function renderSecondLayer(Label $label, ?ServerRequestInterface $request): string
    {
        $id = 'ai-label-' . bin2hex(random_bytes(6));
        return '<button type="button" class="ai-label__more" popovertarget="' . $id . '" aria-controls="' . $id . '" aria-expanded="false">'
            . '<svg class="ai-label__more-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
            . '<path fill="currentColor" fill-rule="evenodd" d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0Zm0 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM7 7h2v5H7V7Zm1-3.25a1.13 1.13 0 1 1 0 2.25 1.13 1.13 0 0 1 0-2.25Z"/></svg>'
            . '<span class="ai-label__sr-only">' . self::escape($this->context->translate('secondLayer.button', $request)) . '</span>'
            . '</button>'
            . '<span id="' . $id . '" class="ai-label__details" popover>'
            . self::escape($this->context->translate('secondLayer.' . $label->type, $request))
            . '</span>';
    }

    private static function symbolPath(): string
    {
        if (self::$symbolPath === null) {
            $svg = (string)@file_get_contents(dirname(__DIR__, 2) . '/' . self::SYMBOL_FILE);
            preg_match_all('/\sd="([^"]+)"/', $svg, $matches);
            self::$symbolPath = self::escape(implode(' ', $matches[1] ?? []));
        }
        return self::$symbolPath;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
