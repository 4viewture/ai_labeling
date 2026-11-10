<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Domain\Label;
use FourViewture\AiLabeling\Domain\Labeling;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

/**
 * Resolves the label of "any" record for ViewHelpers / DataProcessor:
 *
 * - Extbase model: table via the Extbase DataMap, uid = _localizedUid if present (translation).
 *   Models do not know the AI fields -> they are loaded by uid (cached per request).
 * - array (+ table): uses the AI fields of the array if present (e.g. $cObj->data), else loads by uid
 *   (_LOCALIZED_UID / uid).
 * - TYPO3 13.4+/14 Record API (TYPO3\CMS\Core\Domain\RecordInterface): table = getMainType(),
 *   fields via has()/get(), else loaded by localized uid.
 * - table + uid: loaded and overlaid into the current frontend language (PageRepository).
 *
 * DataMap: DataMapFactory::buildDataMap() exists in 12.4, 13.4 and 14.3 (DataMapper::getDataMap()
 * as fallback); both are @internal Extbase API, so an explicit "table" always wins.
 */
final class RecordLabelResolver
{
    /** @var array<string, array<string, mixed>|false> */
    private array $rows = [];

    public function __construct(
        private readonly LabelRenderer $labelRenderer,
        private readonly FrontendContext $context,
    ) {}

    public function resolve(mixed $record = null, ?string $table = null, ?int $uid = null, ?ServerRequestInterface $request = null): ?Label
    {
        $resolved = $this->resolveRow($record, $table, $uid, $request);
        if ($resolved === null) {
            return null;
        }
        [$row, $table] = $resolved;
        return $this->labelRenderer->labelForRow($row, $table, null, $request);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    public function resolveRow(mixed $record = null, ?string $table = null, ?int $uid = null, ?ServerRequestInterface $request = null): ?array
    {
        $table = $table !== null && $table !== '' ? $table : null;

        if ($record instanceof DomainObjectInterface) {
            $table ??= $this->tableOfModel($record);
            $uid = $this->uidOfModel($record);
            $row = null;
        } elseif (is_object($record) && interface_exists(\TYPO3\CMS\Core\Domain\RecordInterface::class)
            && $record instanceof \TYPO3\CMS\Core\Domain\RecordInterface
        ) {
            $table ??= $record->getMainType();
            $row = $this->fieldsOfRecordObject($record);
            $uid = $record->getComputedProperties()->getLocalizedUid() ?? $record->getUid();
        } elseif (is_array($record)) {
            $row = array_key_exists(Labeling::F_ACTIVE, $record) ? $record : null;
            $uid = (int)($record['_LOCALIZED_UID'] ?? $record['uid'] ?? 0);
            if ($row !== null && $table !== null) {
                return LabelingTables::isLabeled($table) ? [$row, $table] : null;
            }
        } else {
            $row = null;
        }

        if ($table === null || !LabelingTables::isLabeled($table)) {
            return null;
        }
        if ($row !== null) {
            return [$row, $table];
        }
        if ($uid === null || $uid <= 0) {
            return null;
        }
        $row = $this->loadRow($table, $uid, $record === null || is_array($record), $request);
        return $row === null ? null : [$row, $table];
    }

    private function tableOfModel(DomainObjectInterface $model): ?string
    {
        try {
            if (method_exists(DataMapFactory::class, 'buildDataMap')) {
                return GeneralUtility::makeInstance(DataMapFactory::class)->buildDataMap($model::class)->getTableName();
            }
            return GeneralUtility::makeInstance(DataMapper::class)->getDataMap($model::class)->getTableName();
        } catch (\Throwable) {
            return null;
        }
    }

    private function uidOfModel(DomainObjectInterface $model): ?int
    {
        if ($model instanceof AbstractDomainObject) {
            $localized = $model->_getProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID);
            if (is_int($localized) && $localized > 0) {
                return $localized;
            }
        }
        return $model->getUid();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fieldsOfRecordObject(object $record): ?array
    {
        if (!method_exists($record, 'has') || !method_exists($record, 'get') || !$record->has(Labeling::F_ACTIVE)) {
            return null;
        }
        $row = [];
        foreach ([Labeling::F_ACTIVE, Labeling::F_INVOLVEMENT, Labeling::F_LABEL_TEXT] as $field) {
            try {
                $value = $record->has($field) ? $record->get($field) : null;
            } catch (\Throwable) {
                $value = null;
            }
            // select fields may come back as arrays/enums in the Record API
            if (is_array($value)) {
                $value = reset($value);
            }
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            $row[$field] = is_scalar($value) ? $value : null;
        }
        return $row;
    }

    /**
     * @param bool $overlay true: uid may be a default language uid -> overlay into the frontend language
     * @return array<string, mixed>|null
     */
    private function loadRow(string $table, int $uid, bool $overlay, ?ServerRequestInterface $request): ?array
    {
        $languageId = $this->context->getLanguageId($request);
        $key = $table . ':' . $uid . ':' . ($overlay ? $languageId : 'x');
        if (!array_key_exists($key, $this->rows)) {
            $this->rows[$key] = $this->fetch($table, $uid, $overlay && $languageId > 0) ?? false;
        }
        return $this->rows[$key] === false ? null : $this->rows[$key];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $table, int $uid, bool $overlay): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        // The caller already decided to display the record (incl. previews of hidden records)
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (!is_array($row)) {
            return null;
        }
        if ($overlay) {
            try {
                $pageRepository = GeneralUtility::makeInstance(PageRepository::class, GeneralUtility::makeInstance(Context::class));
                $row = $pageRepository->getLanguageOverlay($table, $row) ?? $row;
            } catch (\Throwable) {
                // keep default language row
            }
        }
        return $row;
    }
}
