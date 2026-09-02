<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Data;

use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class PropertyService
{
    /**
     * Загружает связанные элементы для свойства типа «привязка к элементам».
     *
     * @param array<string, mixed>|array<int, array<string, mixed>> $items Один элемент или список.
     * @param string $propCode Код свойства-ссылки.
     * @param array<string, array<string>> $select Настройки полей и свойств связанных элементов.
     */
    public static function fillEnumInfo(
        array &$items,
        string $propCode,
        array $select = ['fields' => [], 'propCodes' => [], 'propFields' => []]
    ): void {
        if (!$propCode || !$items) {
            return;
        }

        $temp = isset($items['ID']) ? [$items] : $items;
        $ids = DataHelper::pluckColumn($temp, 'PROPERTIES.' . $propCode . '.VALUE');
        if (!$ids) {
            return;
        }

        $prop = reset($temp)['PROPERTIES'][$propCode] ?? [];
        if (($prop['PROPERTY_TYPE'] ?? '') !== 'E') {
            return;
        }

        $itemById = IblockManager::getListWithProps(
            filter: [
                'ID' => $ids,
                'IBLOCK_ID' => $prop['LINK_IBLOCK_ID'],
                'ACTIVE' => 'Y',
            ],
            select: $select['fields'] ?: [],
            propCodes: $select['propCodes'],
            propFields: $select['propFields'] ?: []
        );

        foreach ($temp as &$item) {
            $item['PROPS'][$propCode] = [];
            $value = $item['PROPERTIES'][$propCode]['VALUE'] ?? null;

            if (is_array($value)) {
                foreach ($value as $id) {
                    if (isset($itemById[$id])) {
                        $item['PROPS'][$propCode][] = $itemById[$id];
                    }
                }
            } elseif (isset($itemById[$value])) {
                $item['PROPS'][$propCode] = $itemById[$value];
            }
        }
        unset($item);

        $items = isset($items['ID']) ? $temp[0] : $temp;
    }
}
