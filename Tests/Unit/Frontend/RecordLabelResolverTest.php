<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Frontend;

use FourViewture\AiLabeling\Domain\Label;
use FourViewture\AiLabeling\Domain\Labeling as L;
use FourViewture\AiLabeling\Frontend\FrontendContext;
use FourViewture\AiLabeling\Frontend\LabelRenderer;
use FourViewture\AiLabeling\Frontend\RecordLabelResolver;
use FourViewture\AiLabeling\Service\LabelTextResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

final class RecordLabelResolverTest extends TestCase
{
    private RecordLabelResolver $subject;

    protected function setUp(): void
    {
        $GLOBALS['TCA']['tx_foo']['columns'][L::F_INVOLVEMENT] = ['config' => ['type' => 'select']];
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturn('Dieser Text wurde mit Hilfe von KI erzeugt.');
        $GLOBALS['LANG'] = $languageService;
        $context = new FrontendContext($this->createStub(LanguageServiceFactory::class));
        $this->subject = new RecordLabelResolver(new LabelRenderer($context, new LabelTextResolver()), $context);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TCA'], $GLOBALS['LANG']);
    }

    #[Test]
    public function arrayWithAiFieldsIsUsedWithoutQuery(): void
    {
        $label = $this->subject->resolve(['uid' => 5, L::F_ACTIVE => 1, L::F_INVOLVEMENT => 3], 'tx_foo');
        self::assertSame(Label::GENERATED, $label?->type);
        self::assertSame('Dieser Text wurde mit Hilfe von KI erzeugt.', $label->text);
    }

    #[Test]
    public function inactiveOrUnknownTablesGiveNoLabel(): void
    {
        self::assertNull($this->subject->resolve(['uid' => 5, L::F_ACTIVE => 0, L::F_INVOLVEMENT => 3], 'tx_foo'));
        self::assertNull($this->subject->resolve(['uid' => 5, L::F_ACTIVE => 1], 'tx_not_labeled'));
        self::assertNull($this->subject->resolve(null));
        self::assertNull($this->subject->resolve('string'));
        self::assertNull($this->subject->resolve(null, 'tx_foo', 0));
    }
}
