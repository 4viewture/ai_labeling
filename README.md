# AI Labeling – EU AI Act Art. 50 for TYPO3

`4viewture/ai-labeling` (extension key `ai_labeling`) adds transparency fields for AI-generated or
AI-modified content to **file metadata** (`sys_file_metadata`), **content elements** (`tt_content`)
and **all other eligible record tables** (pages, news, …), following the guide
[„KI-generierte Inhalte kennzeichnen“](https://digitalzentrum-berlin.de/leitfaden-ki-generierte-inhalte-kennzeichnen)
(Mittelstand-Digital Zentrum Berlin). Transparency obligations apply from 2 August 2026.

Supports **TYPO3 12.4 LTS, 13.4 LTS and 14**, PHP 8.1+.

Editor guide (German): [Documentation/Leitfaden.md](Documentation/Leitfaden.md)

## Example file in file_metadata

![backend_example.png](Documentation/Images/backend_example.png)

## Features

- Own **„KI“ tab** on every type of every eligible table – columns, palettes and tab are added in
  `AfterTcaCompilationEvent` (tables/types of extensions loaded later are covered), database columns
  via `AlterTableDefinitionStatementsEvent` (`sys_file_metadata`/`tt_content` also in `ext_tables.sql`).
- **Eligible tables** (`tables = *`): all tables with types, except `be_*`, `sys_*` (but
  `sys_file_metadata` is always included), `fe_users`, `fe_groups`, cache/index/scheduler/impexp tables,
  `adminOnly`, `readOnly`, `is_static` and root-level (`rootLevel = 1`) tables. Restrict with
  `tables = tt_content,pages,…` or `excludeTables`. Flush caches and run the database compare
  after changing it.
- **Record icon overlay** for all labeled tables (page module, list module, form header, element browser …):
  `ModifyRecordOverlayIconIdentifierEvent` (13+) / `overrideIconOverlay` hook (12). Core status
  overlays (hidden, start/endtime, access, deleted) keep priority. The list module only selects visible
  columns, so `ModifyDatabaseQueryForRecordListingEvent` adds the two needed fields. Rows without the
  fields (e.g. page tree) get no overlay – no extra queries.
- Decision questions from the guide (deepfake criteria, testimonial, product altered,
  public-interest text, human review) with field descriptions.
- **Live assessment** (`<ai-labeling-guide>` web component) on top of the tab: required /
  recommended / optional / none, reasons, resulting label – plus the collapsible guide.
- Label mode: automatic / always / never (warning flash message if a required label is switched off).
- Computed, persisted fields for the frontend: `tx_ailabeling_status`, `tx_ailabeling_active`,
  `tx_ailabeling_reasons` (calculated on save by a DataHandler hook).
- **Automatic detection on upload/replace** (`AfterFileAddedEvent`, `AfterFileReplacedEvent`):
  - IPTC Digital Source Type in XMP/C2PA (`trainedAlgorithmicMedia`, `compositeWithTrainedAlgorithmicMedia`,
    `compositeSynthetic`, `algorithmicallyEnhanced`)
  - C2PA / Content Credentials claim generator
  - PNG text chunks of Stable Diffusion WebUI, ComfyUI, InvokeAI, NovelAI
  - known AI tool names in metadata only (XMP, EXIF, PNG text, ID3, PDF info, MP4 `©too`)
- **File list indicators**
  - Icon overlay via `ModifyIconForResourcePropertiesEvent` (12.4–14): red AI star = labelled,
    outlined star = AI generated/modified but not labelled. Shown wherever the core renders file icons
    (file list, element browser, file references, live search). Core overlays (e.g. missing file) win.
  - Columns *AI labelling status* / *AI label active* selectable in the column selector of the file
    list (metadata fields, readOnly – not part of the edit form).
  - TYPO3 14+: text badge in the name column and `data-ai-labeling*` row attributes via
    `AfterFileListRowPreparedEvent`.
- CLI / scheduler command for existing files: `vendor/bin/typo3 ailabeling:scan [--dry-run] [--storage=1] [--limit=500] [--override-manual]`

## Installation

```bash
composer require 4viewture/ai-labeling
vendor/bin/typo3 extension:setup
```

Fields are `exclude => true` – grant them to editor groups (*Allowed excludefields*).

## Extension configuration

| Setting | Default | Meaning |
|---|---|---|
| `tables` | `*` | Tables with AI fields (`*` or comma list) |
| `excludeTables` | – | Tables to skip |
| `autoDetect` | 1 | Analyse files on upload/replace |
| `autoLabel` | 1 | Set label mode of detected files to "always label" |
| `scanLimitMb` | 32 | Bytes read per file (head + tail half each for larger files) |
| `additionalSignatures` | – | Comma-separated extra tool names |

## Fields

| Field | Type | Notes |
|---|---|---|
| `tx_ailabeling_involvement` | int | 0 none, 1 assisted, 2 modified (AI MODIFIED), 3 generated (AI GENERATED) |
| `tx_ailabeling_tool` | string | tool / model |
| `tx_ailabeling_real_reference`, `_deceptive`, `_synthetic_person`, `_product_altered` | bool | deepfake / product questions |
| `tx_ailabeling_public_interest`, `_human_review`, `_reviewer` | bool/string | text questions |
| `tx_ailabeling_mode` | int | 0 auto, 1 always, 2 never |
| `tx_ailabeling_label_text` | string | empty = default text (`LabelTextResolver`) |
| `tx_ailabeling_detected`, `_detection` | bool/text | automatic detection + evidence |
| `tx_ailabeling_status` | int | computed: 0 none, 1 optional, 2 recommended, 3 required |
| `tx_ailabeling_active` | bool | computed: **render a label in the frontend if 1** |
| `tx_ailabeling_reasons` | csv | computed reason keys |

## Frontend output

Rendered only for records/files with `tx_ailabeling_active = 1`. Label type: `modified` if
`tx_ailabeling_involvement = 2`, otherwise `generated`. Text: `tx_ailabeling_label_text` of the record,
else the default text in the **site language of the request**.

### Setup

- TYPO3 12.4 – 14: include the static template **"AI Labeling: frontend output"**, or
- TYPO3 13.4+: add the site set **`4viewture/ai-labeling`** to the site (`dependencies:` in
  `config/sites/<site>/config.yaml` or the site module). Do not use both.

| Constant / site setting (`plugin.tx_ailabeling.`) | Default | Meaning |
|---|---|---|
| `css.include` | 1 | include `ai-label.css` (`page.includeCSS`) |
| `content.enable` | 1 | label content elements (`tt_content.stdWrap.postUserFunc`) |
| `label.text` | `visible` | `hidden` = text only for screen readers (`ai-label__text--sr-only`), icon visible |
| `label.secondLayer` | 0 | disclosure button with explanation (Popover API, `ai-label.js` keeps `aria-expanded` in sync) |
| `images.burnIn` | 1 | burn the EU "AI" symbol into processed frontend images |
| `images.position` | `top-right` | `top-right`, `top-left`, `bottom-right`, `bottom-left` |
| `images.sizePercent` / `minSize` / `maxSize` | 8 / 24 / 96 | symbol size: % of the shorter edge, clamped (px) |
| `images.altText` | 1 | append the label text to non-empty `alt`, add the label to `<figcaption>` |
| `media.overlay` | 1 | label as overlay at the top of videos; 0 = label below the player |

### Label component

```html
<span class="ai-label ai-label--generated" data-ai-label="generated">
  <svg class="ai-label__icon" aria-hidden="true" focusable="false">…</svg>
  <span class="ai-label__text">KI-generiert</span>
</span>
```

Theming via custom properties on any ancestor (keep text contrast ≥ 4.5:1):

```css
:root { --ai-label-color: #fff; --ai-label-background: #e60005; --ai-label-border-color: #e60005; }
/* or: <body class="ai-label-theme-red"> */
```

Further properties: `--ai-label-font-size` (0.875em), `--ai-label-radius`, `--ai-label-focus-color`,
`--ai-label-overlay-offset`.

### Content elements

`tt_content.stdWrap.postUserFunc = FourViewture\AiLabeling\Frontend\ContentElementLabeler->process`
(TypoScript of the extension, only if `content.enable = 1`). It never wraps existing markup:

1. skips whitespace, comments, the empty anchors `<a id="c123"></a>` and empty spacer elements,
2. adds `data-ai-label="generated|modified"` to the first real element,
3. inserts the label after the heading inside `<header>` (or `.frame-header` of bootstrap_package),
4. otherwise as first child of the first element (`<span class="ai-label-line">`, which takes a full
   row inside flex/grid parents),
5. without element (or for tables, lists, media, void elements) it prepends `<p class="ai-label-line">`.

`stdWrap` has only one `postUserFunc`: if your site already uses `tt_content.stdWrap.postUserFunc`,
set `content.enable = 0` and call the labeler from your own function.

### Images: burned-in symbol

FAL processor `AiLabelImageProcessor` (`$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']`, after
`SvgImageProcessor`/`DeferredBackendImageProcessor`, before `OnlineMediaPreviewProcessor`/`LocalImageProcessor`)
composites the compact EU **"AI" symbol** (not the "AI GENERATED" text icon – no images of text,
WCAG 1.4.5) onto processed images of labelled files:

- `Image.CropScaleMask` / `Image.Preview`, not in backend requests (no backend previews), jpg/png/webp/
  static gif, and the preview images of YouTube/Vimeo files. SVG and animated GIF/WebP are skipped.
- Default **top right** (Code of Practice, Sub-measure 1.2.2 a), 8 % of the shorter edge, 24–96 px,
  margin 2 % (min 4 px). Images with a shorter edge below 3 × `minSize` are not burned (alt text and
  caption still label them). The Code sets no numeric sizes (Measure 1.1 c) – adjust via constants.
- Black or white variant by **measured contrast** of the target corner (black against the darkest 5 %,
  white against the brightest 5 % of 576 samples, WCAG relative luminance); if neither reaches 3:1, the
  black symbol gets an opaque white backing plate.
- If the core would deliver the original unprocessed (no resize needed), a derived file is created.
- GD, Imagick as fallback. Errors: no symbol, warning in the log. PNGs are generated from the official
  SVGs by `Build/rasterize-icons.sh`. GD re-encodes the image (EXIF/ICC are not kept).
- **Invalidation:** when `tx_ailabeling_active`/`involvement` of a file change (DataHandler hook for
  `sys_file_metadata`, `AutoLabeler`/`ailabeling:scan`), all processed files of the original are deleted
  (files and `sys_file_processedfile` rows) and the page cache of all pages referencing the file is
  flushed (`sys_file_reference` + `sys_refindex` → tags `pageId_<pid>`, `<table>_<uid>`,
  `tx_news_uid_<uid>`/`tx_news_pid_<pid>`). Label text changes flush the pages only.
- Changing `images.*` settings does not change the processed file names: clear processed files
  afterwards (*Maintenance → Remove Temporary Assets*, or `vendor/bin/typo3 cleanup:localprocessedfiles`).
- Processed files are shared between sites/contexts with the same processing configuration; a backend
  request never burns the symbol in, so a file processed there with an identical configuration first
  stays without symbol until it is invalidated.

### Images: alt text and captions

Listener on `AfterCacheableContentIsGeneratedEvent` (once per generated page; content API:
`getContent()/setContent()` in TYPO3 14, `getController()->content` in 12.4/13.4):

- every `<img>` whose `src`, `srcset`, `data-src`, `data-srcset` (or `<source srcset>` of its `<picture>`)
  points to a labelled file – original or processed – gets ` (KI-generiert)` (label text in the
  frontend language) appended to a **non-empty** `alt`. `alt=""` (decorative) stays untouched.
- inside `<figure>` with `<figcaption>` the label component is appended to the caption (once per figure).
- URL → file: request-scoped registry fed by `AfterFileProcessingEvent` (dispatched for new and existing
  processed files), DB fallback for other URLs (local storages, `sys_file_processedfile` / `sys_file`,
  filtered by `tx_ailabeling_active`). Only the matched tags are edited (attribute level), no DOM round trip.
- Non-cacheable output (`USER_INT`) is not rewritten.

### Video, audio, online media

`AiLabelMediaRenderer` (priority 1000, registered like the core renderers via
`RendererRegistry::registerRendererClass()`) decorates the renderer that would render a labelled file
(core `VideoTagRenderer`, `AudioTagRenderer`, `YouTubeRenderer`, `VimeoRenderer` or any other
renderer): it outputs the **unchanged** player HTML followed by the label component.

- Video / YouTube / Vimeo: `ai-label--overlay`. Inside `@supports (anchor-name: --x) and (anchor-scope: --x)`
  the label is positioned via CSS anchor positioning at the **top right** of the player
  (Code of Practice, Sub-measure 1.2.2 a), out of flow, `pointer-events: none`, never over the control
  bar (WCAG 2.4.11). `anchor-scope` binds each label to its own player. Without support: small static
  label right after the player.
- Audio (and `media.overlay = 0`): visible label after the player (`ai-label--media`). The spoken
  disclaimer at the beginning of the recording is editorial work (see guide).

**Two-click / privacy placeholders** (e.g. EXT:video_shariff) render the player HTML only after the
click; the label is part of it, but the preview before the click is not a labelled FAL image. Add the
label to the placeholder in your template override, e.g. in `Partials/Media/Type/Video.html` of
video_shariff after `<f:image … />` inside the play link:

```html
<ai:label record="{file}" variant="overlay" />
```

(`variant="overlay"` anchors to the preceding `<img>`; the label text becomes part of the link name.)

### ViewHelpers (news and other records)

Namespace `ai` is registered globally (`http://typo3.org/ns/FourViewture/AiLabeling/ViewHelpers`).

```html
<ai:label record="{newsItem}" />                       <!-- Extbase model -->
<ai:label record="{data}" table="tx_foo" />            <!-- record array -->
<ai:label table="tx_foo" uid="{uid}" />                <!-- uid, overlaid into the frontend language -->
<ai:label record="{record}" />                         <!-- TYPO3 13.4+ Record object -->
<ai:label record="{newsItem}" textVisible="0" />       <!-- icon only, text for screen readers -->
<ai:label record="{file}" variant="overlay" />         <!-- FAL file / file reference (overlay|media) -->

<ai:condition.labeled record="{newsItem}">
    <f:then>…</f:then>
    <f:else>…</f:else>
</ai:condition.labeled>
```

Models do not know the AI fields; they are loaded by uid (`_localizedUid` for translations), cached
per request.

**EXT:news, list view** – `Resources/Private/Partials/List/Item.html` (copy into your site package):

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
      xmlns:ai="http://typo3.org/ns/FourViewture/AiLabeling/ViewHelpers"
      data-namespace-typo3-fluid="true">
…
<div class="header">
    <h3>
        <n:link newsItem="{newsItem}" settings="{settings}" title="{newsItem.title}">
            <span itemprop="headline">{newsItem.title}</span>
        </n:link>
    </h3>
    <ai:label record="{newsItem}" />
</div>
```

**EXT:news, detail view** – `Resources/Private/Templates/News/Detail.html`, after the headline:

```html
<div class="header">
    <h1 itemprop="headline">{newsItem.title}</h1>
    <ai:label record="{newsItem}" />
</div>
```

Keep the label outside of links and headings (accessible names stay clean). Images of the news item
are labelled automatically (burned-in symbol, alt text, figcaption).

**Generic record (FLUIDTEMPLATE / PAGEVIEW)** – DataProcessor alias `ai-label`:

```typoscript
lib.myRecord = FLUIDTEMPLATE
lib.myRecord {
    dataProcessing.10 = ai-label
    # optional: table = tx_foo (default: current table), as = aiLabel
}
```

```html
<f:if condition="{aiLabel.active}">{aiLabel.html -> f:format.raw()}</f:if>
<!-- also available: {aiLabel.type} (generated|modified), {aiLabel.text} -->
```

## Architecture

```
Classes/
  Configuration/TcaFields.php        shared TCA (columns, palettes, tab, SQL)
  Configuration/LabelingTables.php   which tables get the fields
  Detection/AiContentDetector.php    TYPO3-independent metadata analysis
  Service/LabelEvaluator.php         decision logic of the guide (mirrored in label-guide.js)
  Service/AutoLabeler.php            detector -> FAL metadata
  Service/LabelTextResolver.php      label text / defaults (backend: $GLOBALS['LANG'], frontend: site language)
  Frontend/                          LabelRenderer (component), ContentElementLabeler, RecordLabelResolver, settings
  Html/                              targeted string insertion helpers (no DOM round trip)
  ViewHelpers/, DataProcessing/      <ai:label>, <ai:condition.labeled>, dataProcessing ai-label
  EventListener/                     TCA/SQL for all tables, detection, icon overlays, list module select,
                                     image URL registry + alt/figcaption rewriting
  Service/RecordOverlay.php          overlay decision for record icons
  Hooks/DataHandlerHook.php          persist computed status on save, invalidate processed files/pages
  Resource/                          AiLabelImageProcessor + ImageLabelCompositor (burn-in), AiLabelMediaRenderer
  Imaging/Contrast.php               WCAG contrast, symbol variant and placement
  Service/LabelInvalidator.php       processed files + page cache of a file
  Form/Element/GuideInformation.php  FormEngine fieldWizard: guide + live assessment
  Command/ScanCommand.php            ailabeling:scan
```

Compatibility notes: no PHP attributes for listeners/commands (v12), no Fluid `StandaloneView`
(removed in v14), FormEngine node without own constructor (v12 constructor / v13+ `setData()`),
file types as integers instead of `AbstractFile::FILETYPE_*`.

## Limitations

- Detection is heuristic. Metadata is often stripped by editing tools or social networks;
  invisible watermarks (e.g. SynthID) cannot be read. A tool name inside a human-written caption
  can cause a false positive – the editor reviews the result in the AI tab.
- Detection writes the default-language metadata record only.
- Tiles view with thumbnails: the thumbnail replaces the icon, so the overlay is only visible for
  files without preview; the core offers no event for tiles (12–14).
- Record overlay only when the core shows no status overlay (hidden etc. win) and only where the
  rendered row contains the AI fields (not in the page tree).

## Tests

```bash
vendor/bin/phpunit Tests/Unit
```

The unit tests cover the decision logic and the detector (synthetic JPEG/XMP, PNG chunks, C2PA/CBOR, large files).
