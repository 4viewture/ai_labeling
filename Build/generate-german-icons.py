#!/usr/bin/env python3
"""
Generates German variants of the EU AI icons ("KI" instead of "AI") from the official SVGs in
Resources/Public/Icons/EU:

    ai-symbol-*.svg     -> ki-symbol-*.svg     "KI"
    ai-generated-*.svg  -> ki-generated-*.svg  "KI GENERIERT"
    ai-modified-*.svg   -> ki-modified-*.svg   "KI VERÄNDERT"

The official artwork is set in Rubik (big acronym: weight 700, words: weight 600 with tracking –
glyph proportions match within 0.5 %). Letters that exist in the official files are reused
unchanged (A, I, G, E, N, R, T, D, M, O, F); only missing glyphs (K, V, Ä) are taken from Rubik
(SIL Open Font License 1.1) at the same weight, cap height and tracking. The background (disc /
pill) is kept; the pill is stretched to the new word length. Colours/variants (black, white, 50 %)
come from the respective official file.

Usage (needs fonttools + svgpathtools, and the Rubik variable font):
    python3 -m venv .venv && .venv/bin/pip install fonttools svgpathtools
    curl -L -o Rubik.ttf "https://github.com/google/fonts/raw/main/ofl/rubik/Rubik%5Bwght%5D.ttf"
    .venv/bin/python Build/generate-german-icons.py Rubik.ttf
"""
import re
import sys
from pathlib import Path

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from svgpathtools import parse_path

ICONS = Path(__file__).resolve().parent.parent / 'Resources' / 'Public' / 'Icons' / 'EU'
VARIANTS = ['black', 'white', 'black-50', 'white-50']
PATH_RE = re.compile(r'<path class="(?P<cls>[^"]+)" d="(?P<d>[^"]+)"\s*/>')


class Face:
    """One Rubik instance; glyph outlines scaled to a cap height in SVG units."""

    def __init__(self, font_file: str, weight: int):
        self.font = instancer.instantiateVariableFont(TTFont(font_file), {'wght': weight})
        self.glyphs = self.font.getGlyphSet()
        self.cmap = self.font.getBestCmap()
        self.cap = self.bounds('I')[3]

    def name(self, char: str) -> str:
        return self.cmap[ord(char)]

    def bounds(self, char: str):
        pen = BoundsPen(self.glyphs)
        self.glyphs[self.name(char)].draw(pen)
        return pen.bounds

    def advance(self, char: str) -> float:
        return self.font['hmtx'][self.name(char)][0]

    def path(self, char: str, scale: float, left: float, baseline: float) -> str:
        """Outline with its left bounding edge at x=left and the baseline at y=baseline (SVG y down)."""
        x0 = self.bounds(char)[0]
        pen = SVGPathPen(self.glyphs, ntos=lambda v: f'{v:.2f}'.rstrip('0').rstrip('.'))
        transform = (scale, 0, 0, -scale, left - x0 * scale, baseline)
        self.glyphs[self.name(char)].draw(TransformPen(pen, transform))
        return pen.getCommands()


def paths(svg: str):
    return [(m.group('cls'), m.group('d')) for m in PATH_RE.finditer(svg)]


def bbox(d: str):
    x0, x1, y0, y1 = parse_path(d).bbox()
    return x0, x1, y0, y1


def translate(d: str, dx: float) -> str:
    """Moves an official letter path horizontally (absolute coordinates only occur after M/H/L/C)."""
    p = parse_path(d)
    return p.translated(complex(dx, 0)).d()


def layout(word: str, face: Face, scale: float, tracking: float, start: float, official: dict, baseline: float):
    """Places the letters of word; reuses official outlines (dict char -> d) where available."""
    out = []
    x = start
    previous = None
    for char in word:
        if char == ' ':
            previous = None
            continue
        if previous is not None:
            # distance between left edges = advance(prev) - lsb(prev) + lsb(char), scaled, + tracking
            x += (face.advance(previous) - face.bounds(previous)[0] + face.bounds(char)[0]) * scale + tracking
        if char in official:
            d = official[char]
            out.append(translate(d, x - bbox(d)[0]))
        else:
            out.append(face.path(char, scale, x, baseline))
        previous = char
    right = max(bbox(d)[1] for d in out)
    return out, right


def tracking_of(boxes, chars, face: Face, scale: float) -> float:
    """Average extra spacing between letters of an official word (letter-spaced set)."""
    gaps = []
    for (b1, c1), (b2, c2) in zip(zip(boxes, chars), list(zip(boxes, chars))[1:]):
        natural = (face.advance(c1) - face.bounds(c1)[0] + face.bounds(c2)[0]) * scale
        gaps.append((b2[0] - b1[0]) - natural)
    return sum(gaps) / len(gaps)


def main(font_file: str) -> None:
    bold = Face(font_file, 700)
    semi = Face(font_file, 600)

    # --- symbol: "KI" in the disc -----------------------------------------------------------------
    for variant in VARIANTS:
        source = (ICONS / f'ai-symbol-{variant}.svg').read_text()
        items = paths(source)
        disc = next(p for p in items if p[1].startswith('M272.03,100.72'))
        letters = sorted((p for p in items if p is not disc), key=lambda p: bbox(p[1])[0])
        (cls_a, d_a), (cls_i, d_i) = letters
        a, i = bbox(d_a), bbox(d_i)
        cap = a[3] - a[2]
        scale = cap / bold.cap
        tracking = (i[0] - a[0]) - (bold.advance('A') - bold.bounds('A')[0] + bold.bounds('I')[0]) * scale
        glyphs, right = layout('KI', bold, scale, tracking, 0, {'I': d_i}, a[3])
        width = right
        dx = 272.03 - width / 2  # centre "KI" on the disc
        glyphs = [translate(g, dx) for g in glyphs]
        out = source.replace(f'<path class="{cls_a}" d="{d_a}"/>', f'<path class="{cls_a}" d="{glyphs[0]}"/>')
        out = out.replace(f'<path class="{cls_i}" d="{d_i}"/>', f'<path class="{cls_i}" d="{glyphs[1]}"/>')
        (ICONS / f'ki-symbol-{variant}.svg').write_text(out)

    # --- text icons: "KI GENERIERT" / "KI VERÄNDERT" ---------------------------------------------
    official_small = {}
    for name, word in (('generated', 'GENERATED'), ('modified', 'MODIFIED')):
        items = paths((ICONS / f'ai-{name}-black.svg').read_text())
        letters = sorted(items[1:], key=lambda p: bbox(p[1])[0])[2:]
        for char, (_, d) in zip(word, letters):
            official_small.setdefault(char, d)

    for name, word in (('generated', 'GENERIERT'), ('modified', 'VERÄNDERT')):
        for variant in VARIANTS:
            source = (ICONS / f'ai-{name}-{variant}.svg').read_text()
            items = paths(source)
            pill = max(items, key=lambda p: bbox(p[1])[1] - bbox(p[1])[0])
            letters = sorted((p for p in items if p is not pill), key=lambda p: bbox(p[1])[0])
            letter_cls = letters[0][0]
            (_, d_a), (_, d_i) = letters[:2]
            small = letters[2:]
            official_word = 'GENERATED' if name == 'generated' else 'MODIFIED'
            boxes = [bbox(d) for _, d in small]
            a, i = bbox(d_a), bbox(d_i)
            pill_box = bbox(pill[1])

            # big "KI": same left edge as the official "A"
            scale_big = (a[3] - a[2]) / bold.cap
            tracking_big = (i[0] - a[0]) - (bold.advance('A') - bold.bounds('A')[0] + bold.bounds('I')[0]) * scale_big
            big, _ = layout('KI', bold, scale_big, tracking_big, a[0], {'I': d_i}, a[3])

            # word: same start and baseline as the official word
            e = boxes[official_word.index('E')]  # flat letter: exact cap height and baseline (no overshoot)
            scale_small = (e[3] - e[2]) / semi.cap
            tracking_small = tracking_of(boxes, official_word, semi, scale_small)
            word_paths, right = layout(word, semi, scale_small, tracking_small, boxes[0][0], official_small, e[3])

            # stretch the pill: keep the official right padding
            delta = (right - max(b[1] for b in boxes))
            pill_d = re.sub(r'^(M[\d.]+,[\d.]+h)([\d.]+)', lambda m: m.group(1) + f'{float(m.group(2)) + delta:.2f}', pill[1])
            if pill_d == pill[1]:
                raise SystemExit(f'unexpected pill path in ai-{name}-{variant}.svg')

            body = f'<path class="{pill[0]}" d="{pill_d}"/>\n'
            body += '  <g>\n' + ''.join(f'    <path class="{letter_cls}" d="{d}"/>\n' for d in big) + '  </g>\n'
            body += '  <g>\n' + ''.join(f'    <path class="{letter_cls}" d="{d}"/>\n' for d in word_paths) + '  </g>\n'
            head = source[:source.index('<path')]
            head = re.sub(r'viewBox="0 0 ([\d.]+) ([\d.]+)"', lambda m: f'viewBox="0 0 {float(m.group(1)) + delta:.2f} {m.group(2)}"', head)
            (ICONS / f'ki-{name}-{variant}.svg').write_text(head + body + '</svg>\n')
    print('written to', ICONS)


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit(__doc__)
    main(sys.argv[1])
