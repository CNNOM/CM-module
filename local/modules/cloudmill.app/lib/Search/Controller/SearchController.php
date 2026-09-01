<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;
use CloudMill\App\Search\Service\SearchQueryManager;
use CloudMill\App\Search\Service\SearchHistoryService;

final class SearchController extends Controller
{
    public function configureActions(): array
    {
        return [
            'searchProducts' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'clearHistory' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                    ActionFilter\Csrf::class,
                ],
            ],
        ];
    }

    public function searchProductsAction(string $q = '', int $limit = 6): ?array
    {
        if (!Loader::includeModule('iblock')) {
            $this->addError(new Error('Iblock module is not available'));
            return null;
        }

        $query = trim($q);
        if (mb_strlen($query) < 2) {
            return [
                'query' => $query,
                'count' => 0,
                'chips' => [],
                'items' => [],
            ];
        }

        $normalizedQuery = SearchQueryManager::normalizeSearchValue($query);
        $queryStem = SearchQueryManager::buildSearchStem($query);
        $limit = min(max($limit, 1), 10);

        $iblockId = IblockManager::getID('catalog');
        if ($iblockId <= 0) {
            $this->addError(new Error('Catalog iblock not found'));
            return null;
        }

        $chips = [];
        $iblockList = IblockManager::getIBlockList(
            filter: ['ID' => $iblockId],
            preferByID: true
        );
        $iblockName = trim((string)($iblockList[$iblockId]['NAME'] ?? ''));
        if ($iblockName !== '' && SearchQueryManager::matchesSearchValue($iblockName, $normalizedQuery, $queryStem)) {
            $chips[] = $iblockName;
        }

        $sectionFilter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
        ];

        if ($queryStem !== '' && $queryStem !== $normalizedQuery) {
            $sectionFilter[] = [
                'LOGIC' => 'OR',
                ['%NAME' => $normalizedQuery],
                ['%NAME' => $queryStem],
            ];
        } else {
            $sectionFilter['%NAME'] = $normalizedQuery;
        }

        $sectionResult = \CIBlockSection::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            $sectionFilter,
            false,
            ['ID', 'NAME'],
            ['nTopCount' => 6]
        );

        while ($section = $sectionResult->Fetch()) {
            $sectionName = trim((string)($section['NAME'] ?? ''));
            if (
                $sectionName !== ''
                && SearchQueryManager::matchesSearchValue($sectionName, $normalizedQuery, $queryStem)
                && !in_array($sectionName, $chips, true)
            ) {
                $chips[] = $sectionName;
            }
        }

        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
        ];

        if ($queryStem !== '' && $queryStem !== $normalizedQuery) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['%NAME' => $normalizedQuery],
                ['%NAME' => $queryStem],
            ];
        } else {
            $filter['%NAME'] = $normalizedQuery;
        }

        $items = [];
        $result = \CIBlockElement::GetList(
            [
                'SORT' => 'ASC',
                'NAME' => 'ASC',
            ],
            $filter,
            false,
            false,
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );

        $count = (int)$result->SelectedRowsCount();

        while ($item = $result->GetNext()) {
            $pictureId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE']);

            $items[] = [
                'id' => (int)$item['ID'],
                'name' => (string)$item['NAME'],
                'url' => (string)$item['DETAIL_PAGE_URL'],
                'image' => $pictureId > 0 ? (string)\CFile::GetPath($pictureId) : '',
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return [
            'query' => $query,
            'count' => $count,
            'chips' => array_values($chips),
            'items' => $items,
        ];
    }

    public function clearHistoryAction(): ?array
    {
        SearchHistoryService::clear();

        return [
            'success' => true,
        ];
    }
}
