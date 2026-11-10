<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Html;

/**
 * Labels images of labelled files in an HTML document/fragment – targeted edits only:
 *
 * - every <img> whose src / srcset / data-src / data-srcset (or the <source srcset> of its <picture>)
 *   points to a labelled file gets " (KI-generiert)" appended to a non-empty alt. alt="" (decorative)
 *   stays untouched; an alt that already contains the label text stays unchanged (idempotent).
 * - if the image is inside a <figure> with <figcaption>, the label component is appended inside the
 *   figcaption (once per figure).
 *
 * All edits are collected with offsets of the original string and applied from the end – the rest of
 * the markup stays byte-identical (no DOM round trip).
 */
final class ImageLabelRewriter
{
    /**
     * @param \Closure(list<string>): array<string, array{text: string, html: string}> $lookup
     *        receives all candidate URLs of the document, returns URL => label for labelled ones
     */
    public function rewrite(string $html, \Closure $lookup): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }
        if (!preg_match_all('/<img\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $images = [];
        $allUrls = [];
        foreach ($matches[0] as [$tag, $offset]) {
            $urls = array_merge(self::urlsOfTag($tag), self::urlsOfPictureSources($html, $offset));
            $images[] = ['tag' => $tag, 'offset' => $offset, 'urls' => $urls];
            array_push($allUrls, ...$urls);
        }
        if ($allUrls === []) {
            return $html;
        }
        $labels = $lookup(array_values(array_unique($allUrls)));
        if ($labels === []) {
            return $html;
        }

        /** @var list<array{offset: int, length: int, replacement: string}> $edits */
        $edits = [];
        $figures = [];
        foreach ($images as $image) {
            $label = null;
            foreach ($image['urls'] as $url) {
                if (isset($labels[$url])) {
                    $label = $labels[$url];
                    break;
                }
            }
            if ($label === null) {
                continue;
            }
            $newTag = self::appendToAlt($image['tag'], $label['text']);
            if ($newTag !== $image['tag']) {
                $edits[] = ['offset' => $image['offset'], 'length' => strlen($image['tag']), 'replacement' => $newTag];
            }
            $captionEnd = $this->figcaptionEnd($html, $image['offset']);
            if ($captionEnd !== null && !isset($figures[$captionEnd])) {
                $figures[$captionEnd] = true;
                $edits[] = ['offset' => $captionEnd, 'length' => 0, 'replacement' => ' ' . $label['html']];
            }
        }

        usort($edits, static fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
        foreach ($edits as $edit) {
            $html = substr_replace($html, $edit['replacement'], $edit['offset'], $edit['length']);
        }
        return $html;
    }

    public static function appendToAlt(string $tag, string $text): string
    {
        $alt = Tags::getAttribute($tag, 'alt');
        $text = trim($text);
        if ($alt === null || trim($alt) === '' || $text === '' || mb_stripos($alt, $text) !== false) {
            return $tag;
        }
        return Tags::setAttribute($tag, 'alt', rtrim($alt) . ' (' . $text . ')');
    }

    /**
     * Normalized URL paths of src, srcset, data-src, data-srcset of one tag.
     *
     * @return list<string>
     */
    public static function urlsOfTag(string $tag): array
    {
        $urls = [];
        foreach (['src', 'data-src'] as $attribute) {
            $value = Tags::getAttribute($tag, $attribute);
            if ($value !== null && $value !== '') {
                $urls[] = $value;
            }
        }
        foreach (['srcset', 'data-srcset'] as $attribute) {
            $value = Tags::getAttribute($tag, $attribute);
            if ($value === null || trim($value) === '') {
                continue;
            }
            foreach (explode(',', $value) as $candidate) {
                $candidate = preg_split('/\s+/', trim($candidate))[0] ?? '';
                if ($candidate !== '') {
                    $urls[] = $candidate;
                }
            }
        }
        return array_values(array_unique(array_filter(array_map(self::normalizeUrl(...), $urls))));
    }

    /**
     * Path of a URL without scheme/host/query, URL-decoded, without leading slash:
     * "https://example.org/fileadmin/_processed_/a/b/csm_x.jpg?123" -> "fileadmin/_processed_/a/b/csm_x.jpg"
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'data:')) {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        return ltrim(rawurldecode($path), '/');
    }

    /**
     * @return list<string>
     */
    private static function urlsOfPictureSources(string $html, int $imgOffset): array
    {
        $before = substr($html, 0, $imgOffset);
        $pictureStart = strripos($before, '<picture');
        if ($pictureStart === false) {
            return [];
        }
        $pictureEnd = strripos($before, '</picture');
        if ($pictureEnd !== false && $pictureEnd > $pictureStart) {
            return [];
        }
        $inside = substr($before, $pictureStart);
        if (!preg_match_all('/<source\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', $inside, $sources)) {
            return [];
        }
        $urls = [];
        foreach ($sources[0] as $source) {
            array_push($urls, ...self::urlsOfTag($source));
        }
        return $urls;
    }

    /**
     * Offset of "</figcaption>" of the innermost <figure> containing $offset, or null.
     */
    private function figcaptionEnd(string $html, int $offset): ?int
    {
        $search = $offset;
        while (($figureStart = strripos(substr($html, 0, $search), '<figure')) !== false) {
            $openEnd = strpos($html, '>', $figureStart);
            $close = $openEnd === false ? null : Tags::findClosingTag($html, 'figure', $openEnd + 1);
            if ($close !== null && $close['start'] > $offset) {
                $inner = substr($html, $openEnd + 1, $close['start'] - $openEnd - 1);
                // only the caption of this figure (not one of a nested figure)
                $innerWithoutNested = preg_replace_callback('/<figure\b.*?<\/figure\s*>/is', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $inner) ?? $inner;
                if (!preg_match('/<figcaption\b[^>]*>/i', $innerWithoutNested, $match, PREG_OFFSET_CAPTURE)) {
                    return null;
                }
                $captionOpenEnd = $openEnd + 1 + $match[0][1] + strlen($match[0][0]);
                $captionClose = Tags::findClosingTag($html, 'figcaption', $captionOpenEnd);
                if ($captionClose === null || $captionClose['start'] > $close['start']) {
                    return null;
                }
                if (str_contains(substr($html, $captionOpenEnd, $captionClose['start'] - $captionOpenEnd), 'data-ai-label=')) {
                    return null;
                }
                return $captionClose['start'];
            }
            $search = $figureStart;
        }
        return null;
    }
}
