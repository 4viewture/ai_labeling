# Leitfaden: KI-generierte Inhalte in TYPO3 kennzeichnen

Dieser Leitfaden hilft Redakteurinnen und Redakteuren, Dateien und Inhaltselemente nach
**Art. 50 der KI-Verordnung (EU AI Act)** richtig zu kennzeichnen. Er basiert auf dem
[Leitfaden des Mittelstand-Digital Zentrums Berlin](https://digitalzentrum-berlin.de/leitfaden-ki-generierte-inhalte-kennzeichnen).

> **Wichtig:** Die Transparenzpflichten gelten ab dem **2. August 2026**. Verstöße können
> Bußgelder nach sich ziehen. Dieser Leitfaden ist eine Orientierungshilfe – keine
> rechtsverbindliche Auskunft und keine Rechtsberatung. Künstlerische und satirische
> Ausnahmen werden hier nicht behandelt.

---

## 1. Wo finde ich die Felder?

Jede Datei (Dateiliste → Metadaten bearbeiten), jedes Inhaltselement und jeder andere Datensatz
(z. B. Seiten, News) hat einen eigenen Reiter **„KI“**. Oben im Reiter zeigt eine Box laufend die **Einschätzung** an, während Sie die
Fragen beantworten. Über „Leitfaden anzeigen“ klappen Sie die Kurzfassung dieses Leitfadens auf.

| Bereich | Felder |
|---|---|
| KI-Beteiligung | Einsatz von KI, KI-Werkzeug / Modell |
| Deepfake-Prüfung | Bezug zu realen/plausiblen Personen, Orten, Ereignissen · Täuschungspotenzial · KI-Person als echte Person (Testimonial) · Produkt verändert |
| Texte von öffentlichem Interesse | Öffentliches Interesse · Inhaltlich menschlich geprüft · Redaktionell verantwortlich |
| Kennzeichnung | Automatisch / Immer / Nicht kennzeichnen · Kennzeichnungstext · Interne Notizen |
| Automatische Erkennung | Nur sichtbar, wenn die Datei beim Upload als KI-Inhalt erkannt wurde |

---

## 2. Schritt für Schritt

### Schritt 1 – Einsatz von KI wählen

| Auswahl | Wann? | Pflicht? |
|---|---|---|
| **Keine KI** | Kein KI-Werkzeug beteiligt | Nein |
| **KI-unterstützt** | Nur technische Anpassungen: Farbkorrektur, Hintergrundgestaltung, Skalierung, Schärfung, Komprimierung, Rechtschreibprüfung | Nein |
| **Mit KI verändert** | KI hat Inhalte verändert (z. B. generative Füllung, Personen/Objekte ergänzt, Text umgeschrieben) | Prüfen → Schritt 2 |
| **Mit KI erzeugt** | Inhalt wurde (überwiegend) von KI erzeugt | Prüfen → Schritt 2 |

Tragen Sie zusätzlich das Werkzeug ein (z. B. „Midjourney v7“, „ChatGPT“), damit später
nachvollziehbar ist, womit der Inhalt entstand.

### Schritt 2 – Bild, Audio, Video: Ist es ein Deepfake?

Ein Deepfake liegt vor, wenn **beide** Kriterien zutreffen:

1. **Bezug zu real existierenden oder plausiblen Personen, Orten oder Ereignissen** –
   eine bekannte Person ist nicht nötig, auch erfundene, realistisch wirkende Menschen zählen.
2. **Täuschungspotenzial** – der Inhalt kann für echt gehalten werden.

| Beispiel | Ergebnis |
|---|---|
| Realistisches Foto einer „Mitarbeiterin“, die es nicht gibt | **Pflicht** |
| KI-Person als zufriedener Kunde (Testimonial) | **Pflicht** (+ wettbewerbsrechtliches Risiko) |
| Voice-Clone im Podcast oder Marketingvideo | **Pflicht** |
| Fotorealistische, aber fiktive Landschaft | umstritten → **im Zweifel kennzeichnen** |
| Comic, Fantasie-Motiv, Icon, Infografik, abstrakte Grafik | in der Regel keine Pflicht |

**Produktbilder:** Pflicht, wenn die KI das **Produkt selbst oder seine Nutzung** verändert
(Farbe, Material, Größe, Zubehör, Nutzungssituation). Achtung: Irreführende Darstellungen können
auch mit Kennzeichnung wettbewerbsrechtlich problematisch sein.

### Schritt 3 – Texte: Öffentliches Interesse ohne Prüfung?

Kennzeichnungspflichtig ist ein KI-Text, wenn er

- die Öffentlichkeit über **Themen von öffentlichem Interesse** informieren soll
  (öffentliche Sicherheit, Gesundheit, Umwelt, Verbraucherschutz, Krisenkommunikation,
  allgemeine politische und wirtschaftliche Fragen) **und**
- **ohne inhaltliche menschliche Prüfung** veröffentlicht wird.

Rein werbliche oder unternehmensbezogene Texte fallen in der Regel nicht darunter – außer sie
wirken wie unabhängige Berichterstattung.

**Ausnahme:** Hat eine Person oder Organisation den Text **inhaltlich geprüft und trägt die
redaktionelle Verantwortung**, entfällt die Pflicht. Ein flüchtiges Gegenlesen genügt nicht.
Tragen Sie dann die verantwortliche Person ein.

> Für DRK-Seiten typisch: Meldungen zu Blutspende, Katastrophenschutz, Erste Hilfe oder
> Gesundheit sind Themen von öffentlichem Interesse. KI-Entwürfe hier immer inhaltlich prüfen –
> oder kennzeichnen.

### Schritt 4 – Kennzeichnung festlegen

| Einstellung | Wirkung |
|---|---|
| **Automatisch** (Standard) | Gekennzeichnet wird, wenn die Einschätzung „erforderlich“ oder „empfohlen“ ergibt |
| **Immer kennzeichnen** | Freiwillige Kennzeichnung – z. B. bei Ratgebern mit Rechts- oder Gesundheitsbezug, Pressemitteilungen, Kolumnen |
| **Nicht kennzeichnen** | Nur in begründeten Ausnahmefällen. Ist die Kennzeichnung erforderlich, warnt TYPO3 beim Speichern – Begründung in den Notizen festhalten |

Den **Kennzeichnungstext** können Sie leer lassen; dann wird der Standardtext verwendet:

| Inhalt | KI erzeugt | KI verändert |
|---|---|---|
| Text, Inhaltselement, PDF | „Dieser Text wurde mit Hilfe von KI erzeugt.“ | „Dieser Text wurde mit Hilfe von KI bearbeitet.“ |
| Bild, Video | „KI-generiert“ (EU-Icon „AI GENERATED“) | „Mit KI verändert“ (EU-Icon „AI MODIFIED“) |
| Audio | „Diese Aufnahme enthält KI-generierte Inhalte.“ | dto. |

---

## 3. Die Einschätzung verstehen

| Ergebnis | Bedeutung | Gekennzeichnet bei „Automatisch“? |
|---|---|---|
| Keine Kennzeichnung nötig | Keine KI oder nur technische Anpassungen | nein |
| Kennzeichnung freiwillig | KI genutzt, aber kein Pflicht-Kriterium | nein |
| Kennzeichnung empfohlen | Nur ein Deepfake-Kriterium trifft zu – im Zweifel kennzeichnen | **ja** |
| Kennzeichnung erforderlich | Deepfake, Testimonial, verändertes Produkt oder ungeprüfter Text von öffentlichem Interesse | **ja** |

---

## 4. Automatische Erkennung beim Upload

Viele KI-Anbieter betten Herkunftsinformationen in die Datei ein. Die Erweiterung liest beim
Hochladen bzw. Ersetzen einer Datei aus:

- **IPTC „Digital Source Type“** (XMP): `trainedAlgorithmicMedia` → *mit KI erzeugt*,
  `compositeWithTrainedAlgorithmicMedia` / `compositeSynthetic` → *mit KI verändert*,
  `algorithmicallyEnhanced` → *KI-unterstützt*
- **C2PA / Content Credentials** (u. a. OpenAI, Adobe Firefly, Google, Microsoft)
- **Generator-Parameter in PNG-Dateien** (Stable Diffusion WebUI, ComfyUI, InvokeAI, NovelAI)
- **Werkzeugnamen in den Metadaten** (XMP, EXIF, ID3, PDF-Info, MP4) – z. B. Midjourney, DALL·E

Wird etwas gefunden, setzt TYPO3 „Einsatz von KI“, das Werkzeug und **„Immer kennzeichnen“**
und zeigt die gefundenen Merkmale im Bereich „Automatische Erkennung“ an.

**Bitte trotzdem prüfen:** Metadaten gehen beim Bearbeiten, Exportieren oder durch Social-Media-
Plattformen oft verloren. Fehlen sie, erkennt die Erweiterung nichts – die Verantwortung für
die sichtbare Kennzeichnung bleibt bei Ihnen. Umgekehrt kann ein Werkzeugname in einer
Bildbeschreibung (z. B. ein Foto *über* Midjourney) zu einer falschen Erkennung führen.

---

## 4a. KI-Inhalte in Dateiliste, Seiten- und Listenmodul erkennen

| Anzeige | Bedeutung |
|---|---|
| Roter Stern auf dem Datei-Icon | KI-Datei, wird gekennzeichnet |
| Rot umrandeter Stern | KI-Datei, aber derzeit **nicht** gekennzeichnet – bitte prüfen |
| Spalten „KI-Kennzeichnung: Status“ / „… aktiv“ | Über „Spalten anzeigen“ in der Listenansicht einblendbar |
| Badge „KI-generiert“ / „KI-verändert“ neben dem Namen | ab TYPO3 14 |

Derselbe Stern erscheint am Icon von Inhaltselementen im Seitenmodul und von Datensätzen im
Listenmodul. Ist ein Element versteckt oder zeitgesteuert, zeigt TYPO3 vorrangig dieses Symbol.

Das Symbol erscheint auch in der Dateiauswahl (Element-Browser) und bei Dateiverweisen. In der
Kachelansicht mit Vorschaubildern ersetzt das Vorschaubild das Icon – dort ist das Symbol nicht sichtbar.

---

## 5. Wie wird gekennzeichnet? (Darstellung)

- Die Kennzeichnung muss **klar erkennbar** und **spätestens beim ersten Kontakt** sichtbar sein.
- **Bild:** fest eingebettet, etwa am Rand oder in einer Ecke
- **Kurzes Video:** durchgehend sichtbar · **längeres Video:** zu Beginn, wiederholt und nach jedem Schnitt
- **Livestream:** während der gesamten Übertragung
- **Audio:** gesprochener Hinweis zu Beginn, im Player zusätzlich visuell
- **Text:** einheitlich platziert, etwa über dem Text oder an der Überschrift
- Die EU-Kommission stellt seit Juni 2026 kostenlose Icons „AI GENERATED“, „AI MODIFIED“ und „AI“
  bereit (freiwillig). Eigene Kennzeichnungen sind zulässig, wenn sie fest in der Datei eingebettet
  oder als deutlich sichtbares Wasserzeichen umgesetzt sind.

> Die Ausgabe im Frontend wird in einer späteren Version der Erweiterung umgesetzt. Bis dahin gilt:
> Bei eingebetteten Bildern/Videos die Kennzeichnung im Zweifel direkt in die Datei einbetten.

---

## 6. Wer ist wofür verantwortlich?

- **KI-Anbieter:** technische Kennzeichnung (Wasserzeichen, Metadaten)
- **Ihre Organisation:** sichtbare Kennzeichnung im veröffentlichten Inhalt

## 7. Hinweis zum Urheberrecht

KI-generierte Texte und Bilder sind in der Regel **nicht urheberrechtlich geschützt**. Schutz kann
entstehen, wenn ein Mensch das Ergebnis erheblich bearbeitet. Ein rein KI-generiertes Logo lässt
sich aber als Marke anmelden.

## 8. Kurz-Checkliste

1. Text, Bild, Audio, Video oder Chatbot?
2. Bild/Audio/Video: Bezug zu Realem **und** Täuschungspotenzial? → kennzeichnen
3. Text: öffentliches Interesse **und** keine inhaltliche Prüfung? → kennzeichnen
4. Produktbild: Produkt oder Nutzung durch KI verändert? → kennzeichnen
5. Unsicher? → **kennzeichnen** (freiwillig ist immer erlaubt)
