<?php
// Source of the frontend / constants / site set labels (en + de). Run: php Build/labels.php
declare(strict_types=1);

$frontend = [
    'secondLayer.button' => ['More information about this AI label', 'Mehr Informationen zu dieser KI-Kennzeichnung'],
    'secondLayer.generated' => [
        'This content was generated with artificial intelligence (AI). We label AI-generated content in line with Article 50 of the EU AI Act.',
        'Dieser Inhalt wurde mit künstlicher Intelligenz (KI) erzeugt. Wir kennzeichnen KI-Inhalte nach Artikel 50 der KI-Verordnung der EU.',
    ],
    'secondLayer.modified' => [
        'This content was modified with artificial intelligence (AI). We label AI-modified content in line with Article 50 of the EU AI Act.',
        'Dieser Inhalt wurde mit künstlicher Intelligenz (KI) verändert. Wir kennzeichnen KI-Inhalte nach Artikel 50 der KI-Verordnung der EU.',
    ],
    'constants.css.include' => ['Include CSS: load the stylesheet of the AI label (ai-label.css)', 'CSS einbinden: Stylesheet der KI-Kennzeichnung (ai-label.css) laden'],
    'constants.content.enable' => ['Label content elements: add the label to labelled content elements (tt_content.stdWrap.postUserFunc)', 'Inhaltselemente kennzeichnen: Kennzeichnung an gekennzeichneten Inhaltselementen ausgeben (tt_content.stdWrap.postUserFunc)'],
    'constants.label.text' => ['Label text: visible, or hidden (only for screen readers, the icon stays visible)', 'Kennzeichnungstext: sichtbar oder ausgeblendet (nur für Screenreader, das Icon bleibt sichtbar)'],
    'constants.label.secondLayer' => ['Second layer: button with an explanation next to the label', 'Zweite Ebene: Schaltfläche mit Erklärung neben der Kennzeichnung'],
    'constants.images.burnIn' => ['Burn the AI symbol into processed images', 'KI-Symbol in verarbeitete Bilder einbrennen'],
    'constants.images.position' => ['Position of the burned-in symbol', 'Position des eingebrannten Symbols'],
    'constants.images.sizePercent' => ['Symbol size in percent of the shorter image edge', 'Symbolgröße in Prozent der kürzeren Bildkante'],
    'constants.images.minSize' => ['Minimum symbol size (px)', 'Minimale Symbolgröße (px)'],
    'constants.images.maxSize' => ['Maximum symbol size (px)', 'Maximale Symbolgröße (px)'],
    'constants.images.altText' => ['Append the label text to the alternative text and caption of labelled images', 'Kennzeichnungstext an Alternativtext und Bildunterschrift gekennzeichneter Bilder anhängen'],
    'constants.media.overlay' => ['Show the label as overlay on top of videos (anchor positioning, fallback: below the player)', 'Kennzeichnung als Overlay oben auf Videos anzeigen (Anchor Positioning, sonst unter dem Player)'],
];

$set = [
    'label' => ['AI Labeling (EU AI Act Art. 50)', 'KI-Kennzeichnung (KI-Verordnung Art. 50)'],
    'categories.ailabeling' => ['AI labelling', 'KI-Kennzeichnung'],
    'categories.ailabeling.label' => ['Label', 'Kennzeichnung'],
    'categories.ailabeling.images' => ['Images', 'Bilder'],
    'categories.ailabeling.media' => ['Video and audio', 'Video und Audio'],
    'settings.plugin.tx_ailabeling.css.include' => ['Include CSS', 'CSS einbinden'],
    'settings.description.plugin.tx_ailabeling.css.include' => ['Load ai-label.css. Disable only if the site provides own styles for .ai-label.', 'ai-label.css laden. Nur deaktivieren, wenn die Seite eigene Styles für .ai-label mitbringt.'],
    'settings.plugin.tx_ailabeling.content.enable' => ['Label content elements', 'Inhaltselemente kennzeichnen'],
    'settings.description.plugin.tx_ailabeling.content.enable' => ['Adds the label to labelled content elements via tt_content.stdWrap.postUserFunc.', 'Gibt die Kennzeichnung an gekennzeichneten Inhaltselementen über tt_content.stdWrap.postUserFunc aus.'],
    'settings.plugin.tx_ailabeling.label.text' => ['Label text', 'Kennzeichnungstext'],
    'settings.description.plugin.tx_ailabeling.label.text' => ['"Hidden" keeps the text for screen readers only; the icon stays visible.', '„Ausgeblendet“ behält den Text nur für Screenreader; das Icon bleibt sichtbar.'],
    'settings.plugin.tx_ailabeling.label.text.enum.visible' => ['Visible', 'Sichtbar'],
    'settings.plugin.tx_ailabeling.label.text.enum.hidden' => ['Hidden (screen readers only)', 'Ausgeblendet (nur Screenreader)'],
    'settings.plugin.tx_ailabeling.label.secondLayer' => ['Second layer', 'Zweite Ebene'],
    'settings.description.plugin.tx_ailabeling.label.secondLayer' => ['Adds a button with an explanation next to the label (Popover API).', 'Ergänzt eine Schaltfläche mit Erklärung neben der Kennzeichnung (Popover API).'],
    'settings.plugin.tx_ailabeling.images.burnIn' => ['Burn symbol into images', 'Symbol in Bilder einbrennen'],
    'settings.description.plugin.tx_ailabeling.images.burnIn' => ['Composites the EU "AI" symbol onto processed frontend images. Clear processed files after changing image settings.', 'Fügt das EU-Symbol „AI“ in verarbeitete Frontend-Bilder ein. Nach Änderungen an den Bildeinstellungen die verarbeiteten Dateien leeren.'],
    'settings.plugin.tx_ailabeling.images.position' => ['Symbol position', 'Position des Symbols'],
    'settings.description.plugin.tx_ailabeling.images.position' => ['Default top right (Code of Practice, Sub-measure 1.2.2 a).', 'Standard oben rechts (Verhaltenskodex, Teilmaßnahme 1.2.2 a).'],
    'settings.plugin.tx_ailabeling.images.position.enum.top-right' => ['Top right', 'Oben rechts'],
    'settings.plugin.tx_ailabeling.images.position.enum.top-left' => ['Top left', 'Oben links'],
    'settings.plugin.tx_ailabeling.images.position.enum.bottom-right' => ['Bottom right', 'Unten rechts'],
    'settings.plugin.tx_ailabeling.images.position.enum.bottom-left' => ['Bottom left', 'Unten links'],
    'settings.plugin.tx_ailabeling.images.sizePercent' => ['Symbol size (% of shorter edge)', 'Symbolgröße (% der kürzeren Kante)'],
    'settings.description.plugin.tx_ailabeling.images.sizePercent' => ['Limited by minimum and maximum size.', 'Begrenzt durch minimale und maximale Größe.'],
    'settings.plugin.tx_ailabeling.images.minSize' => ['Minimum symbol size (px)', 'Minimale Symbolgröße (px)'],
    'settings.description.plugin.tx_ailabeling.images.minSize' => ['Images smaller than about three times this size are not labelled by burn-in.', 'Bilder, die kleiner als etwa das Dreifache sind, erhalten kein eingebranntes Symbol.'],
    'settings.plugin.tx_ailabeling.images.maxSize' => ['Maximum symbol size (px)', 'Maximale Symbolgröße (px)'],
    'settings.plugin.tx_ailabeling.images.altText' => ['Extend alternative text and caption', 'Alternativtext und Bildunterschrift ergänzen'],
    'settings.description.plugin.tx_ailabeling.images.altText' => ['Appends the label text to non-empty alt attributes and adds the label to the figure caption.', 'Hängt den Kennzeichnungstext an nicht leere alt-Attribute an und ergänzt die Kennzeichnung in der Bildunterschrift.'],
    'settings.plugin.tx_ailabeling.media.overlay' => ['Overlay on videos', 'Overlay auf Videos'],
    'settings.description.plugin.tx_ailabeling.media.overlay' => ['Shows the label at the top of the player (never over the controls). Off: label below the player.', 'Zeigt die Kennzeichnung oben im Player (nie über der Steuerleiste). Aus: Kennzeichnung unter dem Player.'],
];

function xlf(string $original, array $labels, ?string $lang): string
{
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n"
        . "\t" . '<file source-language="en"' . ($lang ? ' target-language="' . $lang . '"' : '')
        . ' datatype="plaintext" original="' . $original . '" product-name="ai_labeling">' . "\n"
        . "\t\t<header/>\n\t\t<body>\n";
    foreach ($labels as $id => [$en, $de]) {
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out .= "\t\t\t" . '<trans-unit id="' . $e($id) . '" resname="' . $e($id) . '">' . "\n"
            . "\t\t\t\t<source>" . $e($en) . "</source>\n"
            . ($lang ? "\t\t\t\t" . '<target state="translated">' . $e($de) . "</target>\n" : '')
            . "\t\t\t</trans-unit>\n";
    }
    return $out . "\t\t</body>\n\t</file>\n</xliff>\n";
}

$base = dirname(__DIR__);
$f = 'EXT:ai_labeling/Resources/Private/Language/locallang.xlf';
file_put_contents($base . '/Resources/Private/Language/locallang.xlf', xlf($f, $frontend, null));
file_put_contents($base . '/Resources/Private/Language/de.locallang.xlf', xlf($f, $frontend, 'de'));
$s = 'EXT:ai_labeling/Configuration/Sets/AiLabeling/labels.xlf';
file_put_contents($base . '/Configuration/Sets/AiLabeling/labels.xlf', xlf($s, $set, null));
file_put_contents($base . '/Configuration/Sets/AiLabeling/de.labels.xlf', xlf($s, $set, 'de'));
echo "written\n";
