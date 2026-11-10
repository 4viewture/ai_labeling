# Acceptance tests: frontend output (Playwright + axe-core)

Browser tests for the AI label on a running TYPO3 instance (12.4, 13.4 or 14). They complement the unit
tests (`Tests/Unit`, insertion rules, alt/figcaption rewriting, contrast, label resolution).

## Test data (create once per instance)

| Scenario | Record | Settings |
|---|---|---|
| `text-header` | text CE with header, `frame_class = default`, *Einsatz von KI* = generated, mode *always* | – |
| `frame-none` | same, `frame_class = none` | – |
| `image` | textmedia CE with one image ≥ 600 px, file metadata: involvement generated, mode *always*, non-empty alternative text, caption | `images.burnIn = 1`, `images.altText = 1` |
| `video` | textmedia CE with a YouTube file, file metadata labelled | `media.overlay = 1` |
| `news-detail` | news record labelled (modified), detail template with `<ai:label record="{newsItem}" />` after the `<h1>` | – |

Set the page paths in `ai-label.scenarios.json` (copy `ai-label.scenarios.example.json`). Labelling via
the backend form or DataHandler (not plain SQL) so that `tx_ailabeling_active` is computed and processed
files / page caches are invalidated.

## Matrix

Every scenario runs in four projects:

| Project | Setup | WCAG |
|---|---|---|
| `desktop` | 1280 × 900 | baseline, axe `wcag2a/aa`, `wcag21aa`, `wcag22aa` |
| `zoom-200` | 640 × 450 viewport, deviceScaleFactor 2 (= 200 % zoom of 1280) | 1.4.4 resize text |
| `reflow-320` | 320 × 640 | 1.4.10 reflow (no horizontal scrolling, label wraps) |
| `forced-colors` | 1280 × 900, `forcedColors: 'active'` | label border/text in system colours, icon visible |

Additionally `text-spacing`: injects the WCAG 1.4.12 bookmarklet styles (line-height 1.5, letter
0.12em, word 0.16em, paragraph 2em) – nothing clipped/overlapping.

## Checks per scenario

**All**
- exactly one `.ai-label` per labelled item, real text in `.ai-label__text` (not via `::before/::after`),
  icon `svg[aria-hidden="true"][focusable="false"]`, no `title` attribute
- axe-core: no violations inside the labelled element
- label not wider than the viewport (`getBoundingClientRect().right <= clientWidth`), no horizontal page overflow
- text contrast ≥ 4.5:1 (axe `color-contrast`), border visible in forced colors

**text-header**: `[data-ai-label="generated"]` on `#c<uid>`; `.ai-label-line` is a child of `<header>`
directly after the heading; the heading's accessible name does **not** contain the label text.

**frame-none**: `<a id="c<uid>">` is untouched, `data-ai-label` on the `<header>`; the column container
has no additional child elements (count of children before/after equal).

**image**
- `img.alt` ends with ` (KI-generiert)` (or the localized text), decorative images (`alt=""`) unchanged
- `figcaption .ai-label` present once
- burned-in symbol: draw the loaded `img` into a canvas, sample the top-right symbol area
  (8 % of the shorter edge, margin 2 %) – a dark or light disc differs ≥ 3:1 from the area just
  outside the symbol
- after unlabelling in the backend, the page shows no label and the processed image no symbol

**video**
- `.ai-label--overlay` is `position: absolute` (with anchor positioning support), inside the player box,
  top inset ≤ 1.5em, **bottom edge above the lower third** of the player (control bar, WCAG 2.4.11)
- `pointer-events: none`: `document.elementFromPoint(centerOfLabel)` is never the label (clicks reach the player)
- two-click placeholders: label visible on the preview if the template uses `variant="overlay"`
- without anchor positioning (Firefox < support, or `@supports` forced off): label is in flow after the player

**news-detail**: label directly after the `<h1>` (sibling inside `.header`), type `modified`.

**second layer** (enable `label.secondLayer` for one run): button ≥ 24 × 24 px, visible focus outline,
Enter opens the popover, `aria-expanded="true"` (async – wait for the `toggle` event), Esc closes and
returns `false`, popover inside the viewport at 320 px.

## Run

```bash
cd Tests/Acceptance
npm install
npx playwright install chromium
BASE_URL=https://example.ddev.site npx playwright test
```
