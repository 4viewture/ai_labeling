<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Detection;

use FourViewture\AiLabeling\Detection\AiContentDetector;
use FourViewture\AiLabeling\Domain\Labeling as L;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AiContentDetectorTest extends TestCase
{
    private static function png(array $textChunks): string
    {
        $chunk = static fn(string $type, string $data): string
            => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0));
        foreach ($textChunks as $keyword => $text) {
            $png .= $chunk('tEXt', $keyword . "\0" . $text);
        }
        $png .= $chunk('IDAT', (string)gzcompress("\0\0\0\0")) . $chunk('IEND', '');
        return $png;
    }

    private static function jpegWithXmp(string $xmpBody): string
    {
        $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . $xmpBody . '</rdf:RDF></x:xmpmeta>';
        $payload = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
        return "\xFF\xD8\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . random_bytes(256) . "\xFF\xD9";
    }

    #[Test]
    public function detectsIptcTrainedAlgorithmicMedia(): void
    {
        $data = self::jpegWithXmp('<rdf:Description Iptc4xmpExt:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia"/>');
        $result = (new AiContentDetector())->detect($data);
        self::assertNotNull($result);
        self::assertSame(L::INVOLVEMENT_GENERATED, $result->involvement);
    }

    #[Test]
    public function detectsCompositeAsModified(): void
    {
        $data = self::jpegWithXmp('<rdf:Description Iptc4xmpExt:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia" xmp:CreatorTool="Adobe Photoshop 26.0"/>');
        $result = (new AiContentDetector())->detect($data);
        self::assertNotNull($result);
        self::assertSame(L::INVOLVEMENT_MODIFIED, $result->involvement);
    }

    #[Test]
    public function detectsAlgorithmicallyEnhancedAsAssistedOnly(): void
    {
        $data = self::jpegWithXmp('<rdf:Description Iptc4xmpExt:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicallyEnhanced"/>');
        self::assertSame(L::INVOLVEMENT_ASSISTED, (new AiContentDetector())->detect($data)?->involvement);
    }

    #[Test]
    public function detectsToolNameInXmp(): void
    {
        $data = self::jpegWithXmp('<rdf:Description xmp:CreatorTool="Midjourney"/>');
        $result = (new AiContentDetector())->detect($data);
        self::assertSame(L::INVOLVEMENT_GENERATED, $result?->involvement);
        self::assertSame('Midjourney', $result?->tool);
    }

    #[Test]
    public function ignoresToolNamesOutsideOfMetadata(): void
    {
        $data = "\xFF\xD8\xFF\xDB" . random_bytes(64) . 'Midjourney' . random_bytes(64) . "\xFF\xD9";
        self::assertNull((new AiContentDetector())->detect($data));
    }

    #[Test]
    public function regularPhotoIsNotDetected(): void
    {
        $data = self::jpegWithXmp('<rdf:Description xmp:CreatorTool="Adobe Lightroom Classic 14.0" Iptc4xmpExt:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture"/>');
        self::assertNull((new AiContentDetector())->detect($data));
    }

    #[Test]
    public function detectsStableDiffusionParametersInPng(): void
    {
        $data = self::png(['parameters' => "a photo of a cat\nSteps: 20, Sampler: Euler a, CFG scale: 7, Seed: 42"]);
        $result = (new AiContentDetector())->detect($data);
        self::assertSame(L::INVOLVEMENT_GENERATED, $result?->involvement);
        self::assertSame('Stable Diffusion WebUI', $result?->tool);
    }

    #[Test]
    public function detectsComfyUiWorkflowInPng(): void
    {
        $data = self::png(['prompt' => '{"3":{"class_type":"KSampler","inputs":{}}}']);
        self::assertSame('ComfyUI', (new AiContentDetector())->detect($data)?->tool);
    }

    #[Test]
    public function readsC2paClaimGenerator(): void
    {
        $generator = 'ChatGPT';
        $cbor = 'claim_generator' . chr(0x60 + strlen($generator)) . $generator
            . 'digitalSourceType' . 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';
        $data = "\xFF\xD8\xFF\xEB" . "\0\0\0\x20jumb\0\0\0\x18jumdc2pa\0\x11\0\x10" . $cbor . "\xFF\xD9";
        $result = (new AiContentDetector())->detect($data);
        self::assertSame(L::INVOLVEMENT_GENERATED, $result?->involvement);
        self::assertSame('OpenAI ChatGPT', $result?->tool);
        self::assertStringContainsString('claim generator: ChatGPT', implode("\n", $result?->evidence ?? []));
    }

    #[Test]
    public function detectsAdditionalSignatures(): void
    {
        $data = self::jpegWithXmp('<rdf:Description xmp:CreatorTool="Inhouse GenAI Studio"/>');
        self::assertNull((new AiContentDetector())->detect($data));
        self::assertSame('Inhouse GenAI Studio', (new AiContentDetector(1024 * 1024, ['Inhouse GenAI Studio']))->detect($data)?->tool);
    }

    #[Test]
    public function detectsFileOnDiskIncludingLargeFileTail(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ailabel');
        // XMP at the end of a file larger than the scan limit (as e.g. in MP4 files with moov at the end)
        file_put_contents($file, str_repeat("\0", 3 * 1024 * 1024) . '<x:xmpmeta>trainedAlgorithmicMedia</x:xmpmeta>');
        try {
            self::assertSame(L::INVOLVEMENT_GENERATED, (new AiContentDetector(1024 * 1024))->detectFile($file)?->involvement);
        } finally {
            unlink($file);
        }
    }
}
