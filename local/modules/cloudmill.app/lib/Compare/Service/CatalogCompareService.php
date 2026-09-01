<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockSection;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class CatalogCompareService
{
    private const EXCLUDED_PROP_CODES = [
        'CML2_LINK',
        'CML2_TRAITS',
        'CML2_ATTRIBUTES',
        'MORE_PHOTO',
        'FILES',
    ];

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
        $ids = $this->normalizeCompareIds($ids);
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

        $products = IblockManager::getList(
            filter: [
                'IBLOCK_ID' => IblockManager::getID('catalog'),
                'ID' => array_values($productIds),
                'ACTIVE' => 'Y',
            ],
            select: ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
            preferByID: true
        );

        $sectionIds = [];
        foreach ($products as $product) {
            $sectionId = (int)($product['IBLOCK_SECTION_ID'] ?? 0);
            if ($sectionId > 0) {
                $sectionIds[$sectionId] = $sectionId;
            }
        }

        $sections = [];
        if ($sectionIds) {
            $sectionList = IblockManager::getSectionList(
                filter: ['ID' => array_values($sectionIds), 'ACTIVE' => 'Y'],
                select: ['ID', 'NAME', 'CODE', 'SECTION_PAGE_URL'],
                preferBy: 'ID'
            );
            $sections = $sectionList;
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
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $offersIblockId = IblockManager::getID('offers');
        $catalogIblockId = IblockManager::getID('catalog');
        if ($offersIblockId <= 0 || $catalogIblockId <= 0 || !Loader::includeModule('iblock')) {
            return $ids;
        }

        $offerIds = [];
        $productIds = [];

        $existingOffers = IblockManager::getList(
            filter: ['IBLOCK_ID' => $offersIblockId, 'ID' => $ids, 'ACTIVE' => 'Y'],
            select: ['ID'],
            preferByID: true
        );

        foreach ($ids as $id) {
            if (isset($existingOffers[$id])) {
                $offerIds[$id] = $id;
                continue;
            }

            $productIds[$id] = $id;
        }

        if ($productIds) {
            $res = CIBlockElement::GetList(
                ['SORT' => 'ASC', 'ID' => 'ASC'],
                [
                    'IBLOCK_ID' => $offersIblockId,
                    'ACTIVE' => 'Y',
                    'PROPERTY_CML2_LINK' => array_values($productIds),
                    'PROPERTY_CML2_LINK.ACTIVE' => 'Y',
                ],
                false,
                false,
                ['ID', 'PROPERTY_CML2_LINK']
            );

            while ($offer = $res->GetNext()) {
                $productId = (int)($offer['PROPERTY_CML2_LINK_VALUE'] ?? 0);
                if ($productId > 0 && !isset($offerIds[$productId])) {
                    $offerIds[$productId] = (int)$offer['ID'];
                }
            }
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (isset($existingOffers[$id])) {
                $normalized[] = $id;
                continue;
            }

            if (isset($offerIds[$id])) {
                $normalized[] = $offerIds[$id];
            }
        }

        return array_values(array_unique($normalized));
    }

    private function loadOffers(array $ids): array
    {
        $offersIblockId = IblockManager::getID('offers');
        if ($offersIblockId <= 0) {
            return [];
        }

        return IblockManager::getList(
            filter: [
                'IBLOCK_ID' => $offersIblockId,
                'ID' => $ids,
                'ACTIVE' => 'Y',
            ],
            select: ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
            preferByID: true
        );
    }

    private function loadProducts(array $ids): array
    {
        $catalogIblockId = IblockManager::getID('catalog');
        if ($catalogIblockId <= 0) {
            return [];
        }

        return IblockManager::getList(
            filter: [
                'IBLOCK_ID' => $catalogIblockId,
                'ID' => $ids,
                'ACTIVE' => 'Y',
            ],
            select: ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
            preferByID: true
        );
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
