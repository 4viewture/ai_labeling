<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Html;

/**
 * Minimal, targeted HTML helpers working on strings (no DOMDocument round trip – the markup outside
 * the touched tag stays byte-identical).
 */
final class Tags
{
    /** Opening tag incl. attributes (quoted values may contain ">"), groups: 1 name, 2 attributes, 3 "/" */
    public const OPENING_TAG = '<([a-zA-Z][a-zA-Z0-9:-]*)((?:\s+[^\s"\'>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*)\s*(\/?)>';

    public const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /**
     * Elements whose content model does not allow a <span> child or whose content is not rendered
     * as such – the label is never inserted into them.
     */
    public const NO_LABEL_CHILD = [
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'colgroup', 'ul', 'ol', 'menu', 'dl', 'select', 'optgroup',
        'datalist', 'picture', 'video', 'audio', 'iframe', 'object', 'svg', 'math', 'textarea', 'script',
        'style', 'template', 'canvas', 'map', 'noscript', 'a', 'button', 'label',
    ];

    /**
     * Matches an opening tag at $offset.
     *
     * @return array{name: string, attributes: string, selfClosing: bool, start: int, end: int, html: string}|null
     */
    public static function openingTagAt(string $html, int $offset): ?array
    {
        if (!preg_match('/\G' . self::OPENING_TAG . '/', $html, $match, 0, $offset)) {
            return null;
        }
        return [
            'name' => strtolower($match[1]),
            'attributes' => $match[2],
            'selfClosing' => $match[3] === '/',
            'start' => $offset,
            'end' => $offset + strlen($match[0]),
            'html' => $match[0],
        ];
    }

    /**
     * Skips whitespace and comments starting at $offset.
     */
    public static function skipWhitespaceAndComments(string $html, int $offset): int
    {
        $length = strlen($html);
        while ($offset < $length) {
            $offset += strspn($html, " \t\r\n\f", $offset);
            if (substr_compare($html, '<!--', $offset, 4) === 0) {
                $close = strpos($html, '-->', $offset + 4);
                if ($close === false) {
                    return $length;
                }
                $offset = $close + 3;
                continue;
            }
            break;
        }
        return $offset;
    }

    /**
     * Position of the closing tag matching the element opened before $offset (same-name nesting aware).
     *
     * @return array{start: int, end: int}|null
     */
    public static function findClosingTag(string $html, string $name, int $offset): ?array
    {
        $depth = 1;
        $pattern = '/<(\/?)' . preg_quote($name, '/') . '(?=[\s>\/])[^>]*>/i';
        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            [$tag, $position] = $match[0];
            $offset = $position + strlen($tag);
            if ($match[1][0] === '/') {
                $depth--;
                if ($depth === 0) {
                    return ['start' => $position, 'end' => $offset];
                }
            } elseif (!str_ends_with($tag, '/>')) {
                $depth++;
            }
        }
        return null;
    }

    public static function getAttribute(string $tagHtml, string $attribute): ?string
    {
        $pattern = '/\s' . preg_quote($attribute, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i';
        if (!preg_match($pattern, $tagHtml, $match)) {
            return preg_match('/\s' . preg_quote($attribute, '/') . '(?=[\s>\/])/i', $tagHtml) ? '' : null;
        }
        // groups: 1 double quoted, 2 single quoted, 3 unquoted (unmatched trailing groups are missing)
        $value = ($match[1] ?? '') !== '' ? $match[1] : ((($match[2] ?? '') !== '') ? $match[2] : ($match[3] ?? ''));
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function hasAttribute(string $tagHtml, string $attribute): bool
    {
        return self::getAttribute($tagHtml, $attribute) !== null;
    }

    public static function hasClass(string $tagHtml, string $class): bool
    {
        $classes = preg_split('/\s+/', trim((string)self::getAttribute($tagHtml, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return in_array($class, $classes, true);
    }

    /**
     * Adds (or replaces) one attribute in an opening tag, everything else stays untouched.
     */
    public static function setAttribute(string $tagHtml, string $attribute, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        $pattern = '/(\s' . preg_quote($attribute, '/') . ')(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?(?=[\s>\/])/i';
        if (preg_match($pattern, $tagHtml)) {
            return (string)preg_replace_callback($pattern, static fn(array $m): string => $m[1] . '="' . $escaped . '"', $tagHtml, 1);
        }
        return (string)preg_replace_callback(
            '/(\s*)(\/?)>$/',
            static fn(array $m): string => ' ' . $attribute . '="' . $escaped . '"' . $m[1] . $m[2] . '>',
            $tagHtml,
            1
        );
    }
}
