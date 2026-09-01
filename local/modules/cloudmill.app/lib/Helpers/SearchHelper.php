<?php
declare(strict_types=1);

namespace CloudMill\App\Helpers;

final class SearchHelper
{
    private const SEARCH_POPULAR_HL_CODE = 'SearchPopular';
    private const BOOLEAN_TRUE = 1;
    private const BOOLEAN_FALSE = 0;

    public static function getPopularQueries(
        int $priorityPoolLimit = 10,
        int $priorityResultLimit = 3,
        int $regularPoolLimit = 10,
        int $regularResultLimit = 3
    ): array {
        $popularFields = self::getHLFieldCodes(self::SEARCH_POPULAR_HL_CODE);
        if (!isset($popularFields['UF_QUERY'])) {
            return [
                'priority' => [],
                'regular' => [],
                'all' => [],
            ];
        }

        $priorityFilter = [];
        $regularFilter = [];

        if (isset($popularFields['UF_ACTIVE'])) {
            $priorityFilter['UF_ACTIVE'] = self::BOOLEAN_TRUE;
            $regularFilter['UF_ACTIVE'] = self::BOOLEAN_TRUE;
        }

        if (isset($popularFields['UF_IS_PRIORITY'])) {
            $priorityFilter['UF_IS_PRIORITY'] = self::BOOLEAN_TRUE;
            $regularFilter['UF_IS_PRIORITY'] = self::BOOLEAN_FALSE;
        }

        $priorityOrder = [
            'ID' => 'ASC',
        ];
        if (isset($popularFields['UF_SORT'])) {
            $priorityOrder = ['UF_SORT' => 'ASC'] + $priorityOrder;
        }

        $regularOrder = [
            'ID' => 'ASC',
        ];
        if (isset($popularFields['UF_COUNT'])) {
            $regularOrder = ['UF_COUNT' => 'DESC'] + $regularOrder;
        }

        $priorityItems = self::getItems(
            filter: $priorityFilter,
            order: $priorityOrder,
            limit: $priorityPoolLimit
        );

        $regularItems = self::getItems(
            filter: $regularFilter,
            order: $regularOrder,
            limit: $regularPoolLimit
        );

        $priorityItems = self::pickRandomItems($priorityItems, $priorityResultLimit);
        $regularItems = self::excludeQueries($regularItems, $priorityItems);
        $regularItems = self::pickRandomItems($regularItems, $regularResultLimit);

        return [
            'priority' => $priorityItems,
            'regular' => $regularItems,
            'all' => array_merge($priorityItems, $regularItems),
        ];
    }

    public static function normalizeSearchValue(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^[:alnum:][:space:]]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    public static function buildSearchStem(string $value): string
    {
        $value = self::normalizeSearchValue($value);
        if ($value === '') {
            return '';
        }

        $words = explode(' ', $value);
        $word = (string)($words[0] ?? '');
        if ($word === '') {
            return '';
        }

        $endings = [
            'иями', 'ями', 'ами', 'ией', 'иях', 'иях', 'ого', 'ему', 'ому', 'ыми', 'ими',
            'ать', 'ять', 'ить', 'еть', 'ы', 'и', 'а', 'я', 'е', 'о', 'у', 'ю', 'ь',
        ];

        foreach ($endings as $ending) {
            if (mb_strlen($word) > mb_strlen($ending) + 2 && str_ends_with($word, $ending)) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($ending));
            }
        }

        return $word;
    }

    public static function matchesSearchValue(string $haystack, string $query, string $stem): bool
    {
        $normalizedHaystack = self::normalizeSearchValue($haystack);
        if ($normalizedHaystack === '') {
            return false;
        }

        if ($query !== '' && mb_stripos($normalizedHaystack, $query) !== false) {
            return true;
        }

        if ($stem !== '' && mb_stripos($normalizedHaystack, $stem) !== false) {
            return true;
        }

        return false;
    }

    private static function getItems(array $filter, array $order, int $limit): array
    {
        $popularFields = self::getHLFieldCodes(self::SEARCH_POPULAR_HL_CODE);
        $select = ['ID', 'UF_QUERY'];

        foreach (['UF_SORT', 'UF_COUNT'] as $fieldCode) {
            if (isset($popularFields[$fieldCode])) {
                $select[] = $fieldCode;
            }
        }

        return HLBlockHelper::getList(
            self::SEARCH_POPULAR_HL_CODE,
            [
                'filter' => $filter,
                'order' => $order,
                'limit' => $limit,
                'select' => $select,
            ]
        );
    }

    private static function getHLFieldCodes(string $hlCode): array
    {
        return HLBlockHelper::getFieldsInfo($hlCode);
    }

    private static function pickRandomItems(array $items, int $limit): array
    {
        if (!$items || $limit < 1) {
            return [];
        }

        $items = array_values($items);
        shuffle($items);

        return array_slice($items, 0, $limit);
    }

    private static function excludeQueries(array $items, array $excludedItems): array
    {
        if (!$excludedItems) {
            return $items;
        }

        $excludedQueries = [];
        foreach ($excludedItems as $item) {
            $query = trim((string)($item['UF_QUERY'] ?? ''));
            if ($query !== '') {
                $excludedQueries[$query] = true;
            }
        }

        return array_values(array_filter($items, static function (array $item) use ($excludedQueries) {
            $query = trim((string)($item['UF_QUERY'] ?? ''));
            return $query !== '' && empty($excludedQueries[$query]);
        }));
    }
}
