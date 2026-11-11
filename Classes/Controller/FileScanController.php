<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Controller;

use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Domain\ScanPlan;
use FourViewture\AiLabeling\Service\AutoLabeler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * AJAX endpoint of the file list context menu action (route ailabeling_scan_file, POST):
 *   identifier = combined identifier of the file, apply = 0 (preview, like --dry-run) | 1 (write)
 *
 * Uses the same detection and decision logic as upload and CLI (AutoLabeler::analyse/plan/apply).
 * Requires the permission to edit the file metadata and the AI fields (exclude fields).
 */
final class FileScanController
{
    private const LLL = 'LLL:EXT:ai_labeling/Resources/Private/Language/locallang_be.xlf:';

    public function __construct(
        private readonly AutoLabeler $autoLabeler,
        private readonly ResourceFactory $resourceFactory,
    ) {}

    public static function canEditMetadata(File $file, ?BackendUserAuthentication $user): bool
    {
        if ($user === null || !$file->isIndexed() || !$file->checkActionPermission('editMeta')) {
            return false;
        }
        return $user->check('tables_modify', 'sys_file_metadata')
            && $user->check('non_exclude_fields', 'sys_file_metadata:' . Labeling::F_INVOLVEMENT);
    }

    public function scanAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || $body === []) {
            $body = json_decode((string)$request->getBody(), true) ?: [];
        }
        $identifier = trim((string)($body['identifier'] ?? ''));
        $apply = (bool)($body['apply'] ?? false);

        try {
            $file = $this->resourceFactory->retrieveFileOrFolderObject($identifier);
        } catch (\Throwable) {
            $file = null;
        }
        if (!$file instanceof File) {
            return $this->error('scan.error', 404);
        }
        if (!self::canEditMetadata($file, $this->user())) {
            return $this->error('scan.denied', 403);
        }

        $result = $this->autoLabeler->analyse($file);
        $response = [
            'success' => true,
            'title' => sprintf($this->translate('scan.title'), $file->getName()),
            'found' => $result !== null,
            'applied' => false,
            'labels' => [
                'apply' => $this->translate('scan.apply'),
                'close' => $this->translate('scan.close'),
                'evidence' => $this->translate('scan.evidence'),
                'changes' => $this->translate('scan.changes'),
            ],
        ];
        if ($result === null) {
            return new JsonResponse($response + ['message' => $this->translate('scan.notFound'), 'evidence' => [], 'changes' => []]);
        }

        $plan = $this->autoLabeler->plan($file, $result);
        if ($apply && $plan->hasChanges()) {
            $this->autoLabeler->apply($file, $plan);
            $response['applied'] = true;
        }
        $message = $this->translate($plan->hasChanges() ? ($response['applied'] ? 'scan.applied' : 'scan.found') : 'scan.noChanges');
        if ($plan->manuallyMaintained) {
            $message .= ' ' . $this->translate('scan.manual');
        }
        return new JsonResponse($response + [
            'message' => $message,
            'tool' => $result->tool,
            'evidence' => $result->evidence,
            'changes' => $this->describeChanges($plan),
            'canApply' => !$response['applied'] && $plan->hasChanges(),
        ]);
    }

    /**
     * @return list<array{field: string, from: string, to: string}>
     */
    private function describeChanges(ScanPlan $plan): array
    {
        $out = [];
        foreach ($plan->changes() as $field => $change) {
            $out[] = [
                'field' => $this->fieldLabel($field),
                'from' => $this->valueLabel($field, $change['from']),
                'to' => $this->valueLabel($field, $change['to']),
            ];
        }
        return $out;
    }

    private function fieldLabel(string $field): string
    {
        $label = (string)($GLOBALS['TCA']['sys_file_metadata']['columns'][$field]['label'] ?? $field);
        return $this->language()?->sL($label) ?: $field;
    }

    private function valueLabel(string $field, string $value): string
    {
        $config = $GLOBALS['TCA']['sys_file_metadata']['columns'][$field]['config'] ?? [];
        if (($config['type'] ?? '') === 'check') {
            return $this->translate((int)$value === 1 ? 'scan.yes' : 'scan.no');
        }
        foreach ((array)($config['items'] ?? []) as $item) {
            if ((string)($item['value'] ?? $item[1] ?? '') === $value) {
                return $this->language()?->sL((string)($item['label'] ?? $item[0] ?? '')) ?: $value;
            }
        }
        return $value === '' ? '–' : $value;
    }

    private function error(string $key, int $status): ResponseInterface
    {
        return new JsonResponse(['success' => false, 'message' => $this->translate($key)], $status);
    }

    private function translate(string $key): string
    {
        return $this->language()?->sL(self::LLL . $key) ?: $key;
    }

    private function language(): ?LanguageService
    {
        $lang = $GLOBALS['LANG'] ?? null;
        return $lang instanceof LanguageService ? $lang : null;
    }

    private function user(): ?BackendUserAuthentication
    {
        $user = $GLOBALS['BE_USER'] ?? null;
        return $user instanceof BackendUserAuthentication ? $user : null;
    }
}
