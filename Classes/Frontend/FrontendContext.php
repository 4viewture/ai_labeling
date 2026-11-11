<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

/**
 * Request related helpers for the frontend output: current request, site language
 * (LanguageService of the *frontend* language, not $GLOBALS['LANG']) and TypoScript settings.
 *
 * Verified against TYPO3 12.4 / 13.4 / 14.3:
 * - request attribute "language" (SiteLanguage) and "frontend.typoscript" (FrontendTypoScript)
 * - LanguageServiceFactory::createFromSiteLanguage()
 * - FrontendTypoScript::getSetupArray() throws in fully cached scope -> defaults are used
 */
final class FrontendContext
{
    /** @var array<string, LanguageService> */
    private array $languageServices = [];

    /** @var array<int, LabelSettings> keyed by spl_object_id of the FrontendTypoScript object */
    private array $settings = [];

    public function __construct(
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function getRequest(?ServerRequestInterface $request = null): ?ServerRequestInterface
    {
        if ($request !== null) {
            return $request;
        }
        $global = $GLOBALS['TYPO3_REQUEST'] ?? null;
        return $global instanceof ServerRequestInterface ? $global : null;
    }

    public function isBackendRequest(?ServerRequestInterface $request = null): bool
    {
        $request = $this->getRequest($request);
        if ($request === null || $request->getAttribute('applicationType') === null) {
            return false;
        }
        try {
            return ApplicationType::fromRequest($request)->isBackend();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getSiteLanguage(?ServerRequestInterface $request = null): ?SiteLanguage
    {
        $language = $this->getRequest($request)?->getAttribute('language');
        return $language instanceof SiteLanguage ? $language : null;
    }

    public function getLanguageId(?ServerRequestInterface $request = null): int
    {
        return $this->getSiteLanguage($request)?->getLanguageId() ?? 0;
    }

    /**
     * ISO 639-1 code of the site language ("de", "en" …); Locale::getLanguageCode() exists in 12.4–14.3.
     */
    public function getLanguageCode(?ServerRequestInterface $request = null): string
    {
        $siteLanguage = $this->getSiteLanguage($request);
        return $siteLanguage !== null ? strtolower($siteLanguage->getLocale()->getLanguageCode()) : '';
    }

    public function getLanguageService(?ServerRequestInterface $request = null): LanguageService
    {
        $siteLanguage = $this->getSiteLanguage($request);
        if ($siteLanguage === null) {
            $lang = $GLOBALS['LANG'] ?? null;
            if ($lang instanceof LanguageService) {
                return $lang;
            }
            return $this->languageServices['default'] ??= $this->languageServiceFactory->create('default');
        }
        $key = $siteLanguage->getLanguageId() . '|' . $siteLanguage->getLocale();
        return $this->languageServices[$key] ??= $this->languageServiceFactory->createFromSiteLanguage($siteLanguage);
    }

    /**
     * @param string $key label key in locallang.xlf (frontend labels)
     */
    public function translate(string $key, ?ServerRequestInterface $request = null): string
    {
        return (string)$this->getLanguageService($request)->sL('LLL:EXT:ai_labeling/Resources/Private/Language/locallang.xlf:' . $key);
    }

    public function getSettings(?ServerRequestInterface $request = null): LabelSettings
    {
        $typoScript = $this->getRequest($request)?->getAttribute('frontend.typoscript');
        if (!$typoScript instanceof FrontendTypoScript) {
            return LabelSettings::defaults();
        }
        $id = spl_object_id($typoScript);
        if (!isset($this->settings[$id])) {
            try {
                $setup = $typoScript->getSetupArray();
                $this->settings[$id] = LabelSettings::fromTypoScript((array)($setup['plugin.']['tx_ailabeling.']['settings.'] ?? []));
            } catch (\Throwable) {
                // fully cached scope: setup array not initialized
                return LabelSettings::defaults();
            }
        }
        return $this->settings[$id];
    }
}
