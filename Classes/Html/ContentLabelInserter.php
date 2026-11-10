<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Html;

/**
 * Inserts a rendered label into the HTML of one content element – without wrapping existing elements
 * and without adding siblings to existing (possibly flex/grid) containers.
 *
 * Rules (in this order):
 *  1. Skip leading whitespace, comments, the empty anchors `<a id="c123"></a>` (frame_class = none,
 *     _LOCALIZED_UID) and other empty spacer elements (`<div class="frame-space-before-…"></div>`).
 *  2. Add data-ai-label="generated|modified" to the first real opening tag.
 *  3. If a header (`<header>` or `.frame-header` of bootstrap_package) with a heading is at the start
 *     (possibly nested in wrapper elements), insert the label after the heading, inside the header –
 *     never inside the heading (keeps the heading's accessible name clean).
 *  4. Otherwise insert it as the first child of the first real element.
 *  5. If there is no element at all (text only), or the first element cannot hold the label
 *     (void element, table, list, media …), prepend `<p class="ai-label-line">` with the label.
 * Empty output stays empty. Already labelled output (data-ai-label on the first element) is unchanged.
 */
final class ContentLabelInserter
{
    /** Class tokens that mark a header container besides the <header> element */
    public const HEADER_CLASSES = ['frame-header'];

    public const ATTRIBUTE = 'data-ai-label';

    /**
     * @param string $type generated|modified
     * @param string $labelHtml rendered label component (LabelRenderer::render())
     */
    public function insert(string $html, string $type, string $labelHtml): string
    {
        if (trim($html) === '') {
            return $html;
        }
        $offset = $this->skipLeadingNoise($html, 0);
        $first = Tags::openingTagAt($html, $offset);
        if ($first === null) {
            // rule 5: no element (or text first)
            return substr($html, 0, $offset) . $this->paragraph($labelHtml) . substr($html, $offset);
        }
        if (Tags::hasAttribute($first['html'], self::ATTRIBUTE)) {
            return $html;
        }

        // rule 2
        $firstTag = Tags::setAttribute($first['html'], self::ATTRIBUTE, $type);
        $html = substr($html, 0, $first['start']) . $firstTag . substr($html, $first['end']);
        $first['end'] = $first['start'] + strlen($firstTag);
        $first['html'] = $firstTag;

        if ($this->cannotHoldLabel($first)) {
            // rule 5 (fallback): element without suitable content model
            return substr($html, 0, $first['start']) . $this->paragraph($labelHtml) . substr($html, $first['start']);
        }

        // rule 3: header at the start (the first element itself or nested in wrappers)
        $insertAt = $this->positionAfterHeading($html, $first);
        if ($insertAt === null) {
            // rule 4: first child of the first real element
            $insertAt = $first['end'];
        }
        return substr($html, 0, $insertAt) . $this->line($labelHtml) . substr($html, $insertAt);
    }

    private function skipLeadingNoise(string $html, int $offset): int
    {
        while (true) {
            $offset = Tags::skipWhitespaceAndComments($html, $offset);
            $tag = Tags::openingTagAt($html, $offset);
            if ($tag === null || $tag['selfClosing'] || in_array($tag['name'], Tags::VOID_ELEMENTS, true)) {
                return $offset;
            }
            $afterEmpty = $this->endOfEmptyElement($html, $tag);
            if ($afterEmpty === null) {
                return $offset;
            }
            $offset = $afterEmpty;
        }
    }

    /**
     * Returns the end position if the element has no content (only whitespace) – e.g. `<a id="c1"></a>`.
     *
     * @param array{name: string, end: int} $tag
     */
    private function endOfEmptyElement(string $html, array $tag): ?int
    {
        $inner = Tags::skipWhitespaceAndComments($html, $tag['end']);
        if (preg_match('/\G<\/' . preg_quote($tag['name'], '/') . '\s*>/i', $html, $match, 0, $inner)) {
            return $inner + strlen($match[0]);
        }
        return null;
    }

    /**
     * @param array{name: string, selfClosing: bool} $tag
     */
    private function cannotHoldLabel(array $tag): bool
    {
        return $tag['selfClosing']
            || in_array($tag['name'], Tags::VOID_ELEMENTS, true)
            || in_array($tag['name'], Tags::NO_LABEL_CHILD, true)
            || preg_match('/^h[1-6]$/', $tag['name']) === 1;
    }

    /**
     * @param array{name: string, html: string, start: int, end: int, selfClosing: bool} $first
     */
    private function positionAfterHeading(string $html, array $first): ?int
    {
        $tag = $first;
        // descend through leading wrapper elements (only tags/whitespace before the header)
        for ($depth = 0; $depth < 10; $depth++) {
            if ($this->isHeaderContainer($tag)) {
                return $this->afterFirstHeadingInside($html, $tag);
            }
            if ($tag['selfClosing'] || in_array($tag['name'], Tags::VOID_ELEMENTS, true)) {
                return null;
            }
            $next = Tags::openingTagAt($html, $this->skipLeadingNoise($html, $tag['end']));
            if ($next === null) {
                return null;
            }
            $tag = $next;
        }
        return null;
    }

    /**
     * @param array{name: string, html: string} $tag
     */
    private function isHeaderContainer(array $tag): bool
    {
        if ($tag['name'] === 'header') {
            return true;
        }
        foreach (self::HEADER_CLASSES as $class) {
            if (Tags::hasClass($tag['html'], $class)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array{name: string, end: int} $header
     */
    private function afterFirstHeadingInside(string $html, array $header): ?int
    {
        $close = Tags::findClosingTag($html, $header['name'], $header['end']);
        if ($close === null) {
            return null;
        }
        $inner = substr($html, $header['end'], $close['start'] - $header['end']);
        if (!preg_match('/<h([1-6])(?=[\s>])/i', $inner, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $headingStart = $header['end'] + $match[0][1];
        $headingClose = Tags::findClosingTag($html, 'h' . $match[1][0], $headingStart + strlen($match[0][0]));
        if ($headingClose === null || $headingClose['end'] > $close['start']) {
            return null;
        }
        return $headingClose['end'];
    }

    private function line(string $labelHtml): string
    {
        return '<span class="ai-label-line">' . $labelHtml . '</span>';
    }

    private function paragraph(string $labelHtml): string
    {
        return '<p class="ai-label-line">' . $labelHtml . '</p>';
    }
}
