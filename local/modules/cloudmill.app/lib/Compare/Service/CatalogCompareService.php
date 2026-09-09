<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use CloudMill\App\Catalog\Service\CatalogService;
use CloudMill\App\Catalog\Service\OffersService;
use CloudMill\App\Infrastructure\Storage\CookieListStorage;

final class CatalogCompareService
{
    private const EXCLUDED_PROP_CODES = [
        'CML2_LINK',
        'CML2_TRAITS',
        'CML2_ATTRIBUTES',
        'MORE_PHOTO',
        'FILES',
    ];

    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly OffersService $offersService,
    ) {
    }

    public function getPageData(array $ids, ?string $sectionCode = null): array
    {
        $items = $this->getCompareItems($ids);
        $groups = $this->groupItemsBySection($items);
        $activeSection = $this->getActiveSection($groups, $sectionCode);
        $activeItems = $activeSection['ITEMS'] ?? [];
        $rows = $this->buildRows($activeItems);
        $this->removeEmptyProps($rows);

        return [
            'items' => $items,
            'groupedItems' => $groups,
            'activeSection' => $activeSection,
            'activeItems' => $activeItems,
            'rows' => $rows,
            'count' => count($items),
            'isEmpty' => !$items,
        ];
    }

    public function getCompareItems(array $ids): array
    {
        $ids = CookieListStorage::normalizeIds($ids, PHP_INT_MAX);
        if (!$ids) {
            return [];
        }

        $offers = $this->loadOffers($ids);
        $products = $this->loadProducts($ids);

        if ($offers) {
            $this->fillProductInfo($offers);
            $this->fillOfferData($offers);
        }

        if ($products) {
            $this->fillDirectProductData($products);
        }

        $items = [];
        foreach ($ids as $id) {
            if (isset($offers[$id])) {
                $items[$id] = $offers[$id];
                continue;
            }

            if (isset($products[$id])) {
                $items[$id] = $products[$id];
            }
        }

        return array_values($items);
    }

    public function fillProductInfo(array &$items): void
    {
        $productIds = [];
        foreach ($items as $item) {
            $productId = (int)($item['PROPERTIES']['CML2_LINK']['VALUE'] ?? $item['ID'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if (!$productIds) {
            return;
        }

        $products = $this->catalogService->findByIds(array_values($productIds));

        $sectionIds = [];
        foreach ($products as $product) {
            $sectionId = (int)($product['IBLOCK_SECTION_ID'] ?? 0);
            if ($sectionId > 0) {
                $sectionIds[$sectionId] = $sectionId;
            }
        }

        $sections = [];
        if ($sectionIds) {
            $sectionList = $this->catalogService->getSections(
                filter: ['ID' => array_values($sectionIds), 'ACTIVE' => 'Y'],
                limit: 0,
                select: ['CODE', 'SECTION_PAGE_URL']
            );
            $sections = array_column($sectionList, null, 'ID');
        }

        foreach ($items as &$item) {
            $productId = (int)($item['PROPERTIES']['CML2_LINK']['VALUE'] ?? $item['ID'] ?? 0);
            $product = $products[$productId] ?? null;
            $section = null;

            if ($product) {
                $sectionId = (int)($product['IBLOCK_SECTION_ID'] ?? 0);
                $section = $sections[$sectionId] ?? null;
            }

            $item['PRODUCT'] = $product ?: [];
            $item['SECTION'] = [
                'ID' => (int)($section['ID'] ?? 0),
                'CODE' => (string)($section['CODE'] ?? 'other'),
                'NAME' => (string)($section['NAME'] ?? 'Без категории'),
                'URL' => (string)($section['SECTION_PAGE_URL'] ?? ''),
            ];
        }
        unset($item);
    }

    public function fillDirectProductData(array &$items): void
    {
        $this->fillProductInfo($items);

        foreach ($items as &$item) {
            $imageId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE']);
            $item['COMPARE_ID'] = (int)$item['ID'];
            $item['PRODUCT_ID'] = (int)$item['ID'];
            $item['PRODUCT'] = $item['PRODUCT'] ?: $item;
            $item['DETAIL_PAGE_URL'] = (string)$item['DETAIL_PAGE_URL'];
            $item['IMAGE'] = $imageId > 0 ? (string)\CFile::GetPath($imageId) : '';
            $item['DISPLAY_PROPS'] = $this->extractDisplayProps($item);
        }
        unset($item);
    }

    public function fillOfferData(array &$items): void
    {
        foreach ($items as &$item) {
            $product = $item['PRODUCT'] ?? [];
            $imageId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE'] ?: ($product['PREVIEW_PICTURE'] ?? 0) ?: ($product['DETAIL_PICTURE'] ?? 0));
            $item['COMPARE_ID'] = (int)$item['ID'];
            $item['PRODUCT_ID'] = (int)($product['ID'] ?? 0);
            $item['NAME'] = (string)($product['NAME'] ?? $item['NAME']);
            $item['DETAIL_PAGE_URL'] = (string)($product['DETAIL_PAGE_URL'] ?? $item['DETAIL_PAGE_URL']);
            $item['IMAGE'] = $imageId > 0 ? (string)\CFile::GetPath($imageId) : '';
            $item['DISPLAY_PROPS'] = $this->extractDisplayProps($item);
        }
        unset($item);
    }

    public function getDisplayProps(array $itemIds, bool $isCompare = true): array
    {
        $items = $isCompare ? $this->getCompareItems($itemIds) : [];

        return $this->buildRows($items);
    }

    public function removeEmptyProps(array &$propsMatrix): void
    {
        $propsMatrix = array_values(array_filter($propsMatrix, static function (array $row): bool {
            foreach ($row['values'] as $value) {
                if (trim((string)$value) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    public function groupItemsBySection(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $section = $item['SECTION'] ?? [];
            $code = (string)($section['CODE'] ?? 'other');

            if (!isset($groups[$code])) {
                $groups[$code] = [
                    'ID' => (int)($section['ID'] ?? 0),
                    'CODE' => $code,
                    'NAME' => (string)($section['NAME'] ?? 'Без категории'),
                    'COUNT' => 0,
                    'ITEMS' => [],
                ];
            }

            $groups[$code]['COUNT']++;
            $groups[$code]['ITEMS'][] = $item;
        }

        return array_values($groups);
    }

    public function getActiveSection(array $items, ?string $sectionCode): ?array
    {
        if (!$items) {
            return null;
        }

        foreach ($items as $group) {
            if ($sectionCode !== null && $sectionCode !== '' && $group['CODE'] === $sectionCode) {
                return $group;
            }
        }

        return $items[0] ?? null;
    }

    public function getUiItemIds(array $ids): array
    {
        $items = $this->getCompareItems($ids);
        $result = [];

        foreach ($items as $item) {
            $result[] = (int)$item['COMPARE_ID'];
            if ((int)($item['PRODUCT_ID'] ?? 0) > 0) {
                $result[] = (int)$item['PRODUCT_ID'];
            }
        }

        return array_values(array_unique(array_filter($result)));
    }

    public function normalizeCompareIds(array $ids): array
    {
        $ids = CookieListStorage::normalizeIds($ids, PHP_INT_MAX);
        if (!$ids) {
            return [];
        }

        $items = $this->loadProducts($ids) + $this->loadOffers($ids);

        return array_values(array_filter($ids, static fn(int $id): bool => isset($items[$id])));
    }

    private function loadOffers(array $ids): array
    {
        return $this->offersService->findByIds($ids);
    }

    private function loadProducts(array $ids): array
    {
        return $this->catalogService->findByIds($ids);
    }

    private function buildRows(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            foreach ($item['DISPLAY_PROPS'] as $code => $prop) {
                if (!isset($rows[$code])) {
                    $rows[$code] = [
                        'code' => $code,
                        'name' => $prop['NAME'],
                        'values' => [],
                    ];
                }

                $rows[$code]['values'][$item['COMPARE_ID']] = $prop['VALUE'];
            }
        }

        foreach ($rows as &$row) {
            foreach ($items as $item) {
                $row['values'][$item['COMPARE_ID']] = (string)($row['values'][$item['COMPARE_ID']] ?? '');
            }
            $row['isDifferent'] = count(array_unique(array_filter($row['values'], static fn ($value): bool => trim((string)$value) !== ''))) > 1;
        }
        unset($row);

        return array_values($rows);
    }

    private function extractDisplayProps(array $item): array
    {
        $props = [];
        $sources = [
            $item['PRODUCT']['PROPERTIES'] ?? [],
            $item['PROPERTIES'] ?? [],
        ];

        foreach ($sources as $sourceProps) {
            foreach ($sourceProps as $code => $prop) {
                if (in_array($code, self::EXCLUDED_PROP_CODES, true)) {
                    continue;
                }

                $value = $this->stringifyPropertyValue($prop['VALUE'] ?? null);
                if ($value === '') {
                    continue;
                }

                $props[$code] = [
                    'CODE' => $code,
                    'NAME' => (string)($prop['NAME'] ?? $code),
                    'VALUE' => $value,
                ];
            }
        }

        return $props;
    }

    private function stringifyPropertyValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = array_filter(array_map(static fn ($item): string => trim((string)$item), $value), static fn (string $item): bool => $item !== '');

            return implode(', ', $value);
        }

        return trim((string)$value);
    }
}
