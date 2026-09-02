<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Service;

use CloudMill\App\Catalog\Service\CatalogService;
use RuntimeException;

final class SearchService
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    public function searchProducts(string $q = '', int $limit = 6): array
    {
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

        $iblockId = $this->catalogService->getIblockId();
        if ($iblockId <= 0) {
            throw new RuntimeException('Catalog iblock not found');
        }

        $chips = [];
        $iblockName = $this->catalogService->getName();
        if ($iblockName !== '' && SearchQueryManager::matchesSearchValue($iblockName, $normalizedQuery, $queryStem)) {
            $chips[] = $iblockName;
        }

        $nameFilter = SearchQueryManager::buildNameFilter($normalizedQuery, $queryStem);
        $sectionFilter = array_merge($nameFilter, [
            'ACTIVE' => 'Y',
        ]);

        foreach ($this->catalogService->getSections($sectionFilter) as $section) {
            $sectionName = trim((string)($section['NAME'] ?? ''));
            if (
                $sectionName !== ''
                && SearchQueryManager::matchesSearchValue($sectionName, $normalizedQuery, $queryStem)
                && !in_array($sectionName, $chips, true)
            ) {
                $chips[] = $sectionName;
            }
        }

        $products = $this->catalogService->getProducts($sectionFilter);
        $items = [];
        foreach (array_slice($products, 0, $limit) as $product) {
            $pictureId = (int)($product['PREVIEW_PICTURE'] ?: $product['DETAIL_PICTURE']);
            $items[] = [
                'id' => (int)$product['ID'],
                'name' => (string)$product['NAME'],
                'url' => (string)$product['DETAIL_PAGE_URL'],
                'image' => $pictureId > 0 ? (string)\CFile::GetPath($pictureId) : '',
            ];
        }
        $count = count($products);

        return [
            'query' => $query,
            'count' => $count,
            'chips' => array_values($chips),
            'items' => $items,
        ];
    }

    public function clearHistory(): void
    {
        SearchHistoryService::clear();
    }
}
