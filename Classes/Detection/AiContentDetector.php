<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Detection;

use FourViewture\AiLabeling\Domain\DetectionResult;
use FourViewture\AiLabeling\Domain\Labeling;

/**
 * Heuristic detection of AI provenance in file metadata.
 *
 * Sources (in order of reliability):
 *  1. IPTC "Digital Source Type" (XMP, also embedded in C2PA manifests):
 *     trainedAlgorithmicMedia, compositeWithTrainedAlgorithmicMedia, compositeSynthetic, algorithmicallyEnhanced
 *  2. C2PA / Content Credentials manifest (claim generator)
 *  3. Generator parameters in PNG text chunks (Stable Diffusion WebUI, ComfyUI, InvokeAI, NovelAI)
 *  4. Known AI tool names in metadata only (XMP, EXIF, PNG text, ID3, PDF info, MP4 encoder tag)
 *
 * Tool names are deliberately NOT searched in the raw pixel/stream data to avoid false positives.
 * Pure TYPO3-independent PHP so it can be unit-tested and reused.
 */
final class AiContentDetector
{
    private const IPTC_GENERATED = 'trainedAlgorithmicMedia';
    private const IPTC_COMPOSITE = 'compositeWithTrainedAlgorithmicMedia';
    private const IPTC_COMPOSITE_SYNTHETIC = 'compositeSynthetic';
    private const IPTC_ENHANCED = 'algorithmicallyEnhanced';

    /**
     * Signature (case-insensitive, matched as whole word) => tool name
     */
    private const TOOL_SIGNATURES = [
        'DALL-E' => 'OpenAI DALL·E',
        "DALL\u{00B7}E" => 'OpenAI DALL·E',
        'DALLE' => 'OpenAI DALL·E',
        'ChatGPT' => 'OpenAI ChatGPT',
        'GPT-4o' => 'OpenAI GPT-4o',
        'gpt-image-1' => 'OpenAI gpt-image-1',
        'OpenAI Sora' => 'OpenAI Sora',
        'OpenAI' => 'OpenAI',
        'Midjourney' => 'Midjourney',
        'Stable Diffusion' => 'Stable Diffusion',
        'stable-diffusion' => 'Stable Diffusion',
        'StableDiffusion' => 'Stable Diffusion',
        'SDXL' => 'Stable Diffusion XL',
        'Adobe Firefly' => 'Adobe Firefly',
        'Firefly' => 'Adobe Firefly',
        'Made with Google AI' => 'Google AI',
        'Google Gemini' => 'Google Gemini',
        'Google Imagen' => 'Google Imagen',
        'Nano Banana' => 'Google Gemini (Nano Banana)',
        'Imagined with AI' => 'Meta AI',
        'Meta AI' => 'Meta AI',
        'Image Playground' => 'Apple Image Playground',
        'Leonardo.Ai' => 'Leonardo.Ai',
        'Leonardo AI' => 'Leonardo.Ai',
        'Ideogram' => 'Ideogram',
        'FLUX.1' => 'FLUX (Black Forest Labs)',
        'black-forest-labs' => 'FLUX (Black Forest Labs)',
        'Bing Image Creator' => 'Microsoft Bing Image Creator',
        'Microsoft Designer' => 'Microsoft Designer',
        'Copilot Designer' => 'Microsoft Copilot Designer',
        'NovelAI' => 'NovelAI',
        'InvokeAI' => 'InvokeAI',
        'ComfyUI' => 'ComfyUI',
        'AUTOMATIC1111' => 'Stable Diffusion WebUI (AUTOMATIC1111)',
        'Fooocus' => 'Fooocus',
        'RunwayML' => 'Runway',
        'Runway Gen-' => 'Runway',
        'Kling AI' => 'Kling AI',
        'Pika Labs' => 'Pika',
        'Luma Dream Machine' => 'Luma Dream Machine',
        'Synthesia' => 'Synthesia',
        'HeyGen' => 'HeyGen',
        'ElevenLabs' => 'ElevenLabs',
        'Suno AI' => 'Suno',
        'Recraft' => 'Recraft',
        'Krea AI' => 'Krea AI',
    ];

    /**
     * @param list<string> $additionalSignatures extra tool names from extension configuration
     */
    public function __construct(
        private readonly int $scanLimitBytes = 32 * 1024 * 1024,
        private readonly array $additionalSignatures = [],
    ) {}

    public function detectFile(string $path): ?DetectionResult
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $data = $this->readData($path);
        if ($data === '') {
            return null;
        }
        $exifText = $this->readExifText($path, $data);
        return $this->detect($data, $exifText);
    }

    /**
     * @param string $data raw file content (or head + tail of large files)
     * @param string $additionalMetadataText already extracted metadata (e.g. EXIF), searched for tool names
     */
    public function detect(string $data, string $additionalMetadataText = ''): ?DetectionResult
    {
        $evidence = [];
        $tool = null;

        // 1. IPTC digital source type (case-sensitive: "compositeWithTrainedAlgorithmicMedia" has a capital T)
        $iptcGenerated = str_contains($data, self::IPTC_GENERATED);
        $iptcComposite = str_contains($data, self::IPTC_COMPOSITE) || str_contains($data, self::IPTC_COMPOSITE_SYNTHETIC);
        $iptcEnhanced = str_contains($data, self::IPTC_ENHANCED);
        if ($iptcGenerated) {
            $evidence[] = 'IPTC DigitalSourceType: ' . self::IPTC_GENERATED;
        }
        if ($iptcComposite) {
            $evidence[] = 'IPTC DigitalSourceType: '
                . (str_contains($data, self::IPTC_COMPOSITE) ? self::IPTC_COMPOSITE : self::IPTC_COMPOSITE_SYNTHETIC);
        }
        if ($iptcEnhanced) {
            $evidence[] = 'IPTC DigitalSourceType: ' . self::IPTC_ENHANCED;
        }

        // 2. C2PA manifest
        $c2paRegion = $this->extractC2paRegion($data);
        $claimGenerator = null;
        if ($c2paRegion !== null) {
            $claimGenerator = $this->extractCborText($c2paRegion, 'claim_generator');
            $evidence[] = 'C2PA Content Credentials' . ($claimGenerator !== null ? ' (claim generator: ' . $claimGenerator . ')' : '');
        }

        // 3. Generator parameters in PNG text chunks
        $pngChunks = $this->readPngTextChunks($data);
        $generatorTool = $this->detectGeneratorParameters($pngChunks);
        if ($generatorTool !== null) {
            $evidence[] = 'PNG text chunk with generation parameters (' . $generatorTool . ')';
        }

        // 4. Tool names in metadata
        $metadataText = implode("\n", array_filter([
            $additionalMetadataText,
            $this->extractXmp($data),
            implode("\n", array_map(static fn(string $k, string $v): string => $k . ': ' . $v, array_keys($pngChunks), $pngChunks)),
            $this->extractId3($data),
            $this->extractPdfInfo($data),
            $this->extractMp4Encoder($data),
            $c2paRegion !== null ? self::printable($c2paRegion) : '',
        ]));
        // CBOR length prefixes are printable characters (e.g. "g" = 0x67) and break word boundaries,
        // therefore the parsed claim generator is matched separately
        $signatureTool = $this->matchToolSignature((string)$claimGenerator) ?? $this->matchToolSignature($metadataText);
        if ($signatureTool !== null) {
            $evidence[] = 'AI tool signature in metadata: ' . $signatureTool;
        }

        $tool = $signatureTool ?? $generatorTool ?? $claimGenerator;

        if ($iptcGenerated || $generatorTool !== null) {
            $involvement = Labeling::INVOLVEMENT_GENERATED;
        } elseif ($iptcComposite) {
            $involvement = Labeling::INVOLVEMENT_MODIFIED;
        } elseif ($signatureTool !== null) {
            $involvement = Labeling::INVOLVEMENT_GENERATED;
        } elseif ($iptcEnhanced) {
            $involvement = Labeling::INVOLVEMENT_ASSISTED;
        } else {
            return null;
        }

        return new DetectionResult($involvement, $tool, array_values(array_unique($evidence)));
    }

    private function readData(string $path): string
    {
        $size = (int)filesize($path);
        if ($size <= $this->scanLimitBytes) {
            return (string)file_get_contents($path);
        }
        $half = intdiv($this->scanLimitBytes, 2);
        $head = (string)file_get_contents($path, false, null, 0, $half);
        $tail = (string)file_get_contents($path, false, null, $size - $half, $half);
        return $head . "\n" . $tail;
    }

    private function readExifText(string $path, string $data): string
    {
        if (!function_exists('exif_read_data')) {
            return '';
        }
        $isJpeg = str_starts_with($data, "\xFF\xD8");
        $isTiff = str_starts_with($data, "II*\0") || str_starts_with($data, "MM\0*");
        if (!$isJpeg && !$isTiff) {
            return '';
        }
        $exif = @exif_read_data($path, null, false);
        if (!is_array($exif)) {
            return '';
        }
        $lines = [];
        foreach (['Software', 'ImageDescription', 'Artist', 'Make', 'Model', 'UserComment', 'Copyright', 'XPComment', 'XPAuthor'] as $key) {
            if (isset($exif[$key]) && is_string($exif[$key])) {
                $lines[] = 'EXIF ' . $key . ': ' . self::printable(str_replace("\0", '', $exif[$key]));
            }
        }
        return implode("\n", $lines);
    }

    private function extractXmp(string $data): string
    {
        if (preg_match_all('#<x:xm?a?pmeta\b.*?</x:xm?a?pmeta>#s', $data, $matches) === 0) {
            return '';
        }
        return implode("\n", $matches[0]);
    }

    private function extractC2paRegion(string $data): ?string
    {
        // JUMBF superbox "jumb" with description box labelled "c2pa"
        $pos = strpos($data, 'jumb');
        if ($pos === false || !str_contains(substr($data, $pos, 4096), 'c2pa')) {
            return null;
        }
        return substr($data, $pos, 512 * 1024);
    }

    /**
     * Minimal CBOR text-string reader for a given map key.
     */
    private function extractCborText(string $region, string $key): ?string
    {
        $pos = strpos($region, $key);
        if ($pos === false) {
            return null;
        }
        $offset = $pos + strlen($key);
        $head = ord($region[$offset] ?? "\0");
        if ($head >= 0x60 && $head <= 0x77) {
            $length = $head - 0x60;
            $offset++;
        } elseif ($head === 0x78) {
            $length = ord($region[$offset + 1] ?? "\0");
            $offset += 2;
        } elseif ($head === 0x79) {
            $length = unpack('n', substr($region, $offset + 1, 2))[1] ?? 0;
            $offset += 3;
        } else {
            return null;
        }
        $value = trim(self::printable(substr($region, $offset, min($length, 200))));
        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string> keyword => text
     */
    private function readPngTextChunks(string $data): array
    {
        if (!str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            return [];
        }
        $chunks = [];
        $offset = 8;
        $length = strlen($data);
        while ($offset + 8 <= $length) {
            $chunkLength = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            $body = substr($data, $offset + 8, $chunkLength);
            $offset += 12 + $chunkLength;
            if ($type === 'IEND' || strlen($body) < $chunkLength) {
                break;
            }
            $nul = strpos($body, "\0");
            if ($nul === false) {
                continue;
            }
            $keyword = substr($body, 0, $nul);
            $rest = substr($body, $nul + 1);
            $text = null;
            if ($type === 'tEXt') {
                $text = $rest;
            } elseif ($type === 'zTXt') {
                $text = @gzuncompress(substr($rest, 1)) ?: null;
            } elseif ($type === 'iTXt') {
                $compressed = ($rest[0] ?? "\0") === "\1";
                $parts = explode("\0", substr($rest, 2), 3);
                $text = $parts[2] ?? '';
                if ($compressed) {
                    $text = @gzuncompress($text) ?: null;
                }
            }
            if ($text !== null) {
                $chunks[$keyword] = ($chunks[$keyword] ?? '') . $text;
            }
        }
        return $chunks;
    }

    /**
     * @param array<string, string> $chunks
     */
    private function detectGeneratorParameters(array $chunks): ?string
    {
        $parameters = $chunks['parameters'] ?? '';
        if (str_contains($parameters, 'Steps:') && (str_contains($parameters, 'Sampler:') || str_contains($parameters, 'Seed:'))) {
            return 'Stable Diffusion WebUI';
        }
        foreach (['prompt', 'workflow'] as $keyword) {
            if (isset($chunks[$keyword]) && (str_contains($chunks[$keyword], '"class_type"') || str_contains($chunks[$keyword], '"nodes"'))) {
                return 'ComfyUI';
            }
        }
        if (isset($chunks['invokeai_metadata']) || isset($chunks['sd-metadata'])) {
            return 'InvokeAI';
        }
        $comment = $chunks['Comment'] ?? '';
        if (str_contains($comment, '"steps"') && str_contains($comment, '"sampler"')) {
            return 'NovelAI';
        }
        return null;
    }

    private function extractId3(string $data): string
    {
        if (!str_starts_with($data, 'ID3') || strlen($data) < 10) {
            return '';
        }
        $size = ((ord($data[6]) & 0x7F) << 21) | ((ord($data[7]) & 0x7F) << 14) | ((ord($data[8]) & 0x7F) << 7) | (ord($data[9]) & 0x7F);
        return self::printable(str_replace("\0", '', substr($data, 10, min($size, 256 * 1024))));
    }

    private function extractPdfInfo(string $data): string
    {
        if (!str_starts_with($data, '%PDF')) {
            return '';
        }
        preg_match_all('#/(Producer|Creator|Author|Title)\s*\(([^)]{1,300})\)#', $data, $matches, PREG_SET_ORDER);
        return implode("\n", array_map(static fn(array $m): string => 'PDF ' . $m[1] . ': ' . $m[2], $matches));
    }

    private function extractMp4Encoder(string $data): string
    {
        // iTunes-style metadata atom "©too" (encoder) -> "data" atom -> 8 byte header -> UTF-8 text
        $pos = strpos($data, "\xA9too");
        if ($pos === false) {
            return '';
        }
        $dataPos = strpos($data, 'data', $pos);
        if ($dataPos === false || $dataPos - $pos > 16) {
            return '';
        }
        return 'MP4 encoder: ' . trim(self::printable(substr($data, $dataPos + 12, 128)));
    }

    private function matchToolSignature(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $signatures = self::TOOL_SIGNATURES;
        foreach ($this->additionalSignatures as $signature) {
            $signature = trim($signature);
            if ($signature !== '') {
                $signatures[$signature] = $signature;
            }
        }
        foreach ($signatures as $signature => $tool) {
            $pattern = '/(?<![A-Za-z0-9])' . preg_quote($signature, '/') . '(?![A-Za-z0-9])/i';
            if (preg_match($pattern, $text) === 1) {
                return $tool;
            }
        }
        return null;
    }

    private static function printable(string $binary): string
    {
        return (string)preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x80-\xFF]+/', ' ', $binary);
    }
}
