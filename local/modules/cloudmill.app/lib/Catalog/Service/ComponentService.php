<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\SystemException;
use CIBlockSection;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class ComponentService
{
    /**
     * Возвращает массив разделов для вывода табами
     * @param string $iblockCode - код инфоблока
     * @param string|null $curSectionCode - текущая секция, нужна для установки активности
     * @param string|null $parentSectionCode
     * @param string $addParentTab - добавляет корневой таб с указанным названием
     * @param string $urlRewrite - изменяет урл по шаблону /catalog/new/#SECTION_CODE#/
     * @param bool $onlyWithItems - игнор пустых разделов
     * @return array
     */
    public static function getSectionTabsArray(
        string  $iblockCode,
        ?string $curSectionCode,
        ?string $parentSectionCode = '',
        string  $addParentTab = '',
        string  $urlRewrite = '',
        bool    $onlyWithItems = true
    ): array
    {
        $filter = [
            'GLOBAL_ACTIVE' => 'Y',
            'IBLOCK_ID' => IblockManager::getID($iblockCode),
        ];
        if ($onlyWithItems) {
            $filter['CNT_ACTIVE'] = true;
        }
        if ($parentSectionCode) {
            try {
                $parentSection = SectionTable::getList([
                        'filter' => ['CODE' => $parentSectionCode],
                        'select' => ['ID', 'DEPTH_LEVEL', 'NAME']]
                )->fetch();
            } catch (SystemException $e) {
                ExceptionHandler::handle($e);
                return [];
            }
            $filter = array_merge($filter, [
                'DEPTH_LEVEL' => $parentSection['DEPTH_LEVEL'] + 1,
                'SECTION_ID' => $parentSection['ID'],
            ]);

        } else {
            $filter['DEPTH_LEVEL'] = 1;
        }

        $res = CIBlockSection::GetList(
            arFilter: $filter,
            bIncCnt: true,
            arSelect: ['ID', 'CODE', 'NAME', 'SECTION_PAGE_URL', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'UF_*']
        );
        $activeFound = false;
        $sectionTabs = [];
        while ($item = $res->GetNext()) {
            if ($onlyWithItems) { // ignore empty
                if (!$item['ELEMENT_CNT']) continue;
            }

            if ($curSectionCode === $item['CODE']) {
                $item['IS_ACTIVE'] = $activeFound = true;
            }
            if ($urlRewrite) {
                $item['SECTION_PAGE_URL'] = str_replace('#SECTION_CODE#', $item['CODE'], $urlRewrite);
            }
            $sectionTabs[$item['ID']] = $item;
        }

        // добавление "Все"
        if ($addParentTab && $sectionTabs) {
            if ($urlRewrite) {
                $allUrl = str_replace('#SECTION_CODE#/', '', $urlRewrite);
            } else {
                if ($parentSectionCode) {
                    $allUrl = preg_replace('#/[^/]+/?$#', '/' . $parentSectionCode . '/', reset($sectionTabs)['SECTION_PAGE_URL']);
                } else {
                    $allUrl = preg_replace('#/[^/]+/?$#', '/', reset($sectionTabs)['SECTION_PAGE_URL']);
                }
            }
            $sectionTabs = array_merge(['ALL' => [
                'NAME' => $addParentTab,
                'SECTION_PAGE_URL' => $allUrl,
                'IS_ACTIVE' => !$activeFound
            ]], $sectionTabs);
        }
        return $sectionTabs;
    }

    /**
     * Устанавливает СЕО из массива IPROPERTY_VALUES или названия
     * @param string $name - простое название
     * @param ?array $iPropArr - IPROPERTY_VALUES
     * @return void
     */
    public static function setItemMeta(string $name, ?array $iPropArr = []): void
    {
        global $APPLICATION;

        // Определяем тип сущности по первому ключу массива (если есть)
        $entity = 'ELEMENT'; // значение по умолчанию
        if (!empty($iPropArr)) {
            $firstKey = array_key_first($iPropArr);
            if (preg_match('/^(SECTION|ELEMENT)_/', (string)$firstKey, $matches)) {
                $entity = $matches[1];
            }
        }

        // Устанавливаем значения (берём из массива или fallback на $name)
        if (isset($iPropArr[$entity . '_META_TITLE'])) {
            $APPLICATION->SetPageProperty('title', $iPropArr[$entity . '_META_TITLE'] ?? $name);
        } elseif ($name) {
            $APPLICATION->SetPageProperty('title', $name);
        }

        if (isset($iPropArr[$entity . '_PAGE_TITLE'])) {
            $APPLICATION->SetTitle($iPropArr[$entity . '_PAGE_TITLE']);
        } elseif ($name) {
            $APPLICATION->SetTitle($name);
        }

        if (isset($iPropArr[$entity . '_META_KEYWORDS'])) {
            $APPLICATION->SetPageProperty('KEYWORDS', $iPropArr[$entity . '_META_KEYWORDS']);
        }

        if (isset($iPropArr[$entity . '_META_DESCRIPTION'])) {
            $APPLICATION->SetPageProperty('DESCRIPTION', $iPropArr[$entity . '_META_DESCRIPTION']);
        }
    }

    /**
     * Добавляет в массив enum-свойства ключ ITEM с полным инфо об элементе
     * @param $items - массив элементов
     * @param $propCode - код свойства
     * @param array $select - выбор полей и свойств для погрузки. Если propCodes == null, то без свойств
     * @return void
     */
    public static function fillPropEnumInfo(
        &$items, $propCode, array $select = ['fields' => [], 'propCodes' => [], 'propFields' => []]
    ): void
    {
        if (!$propCode || !$items) return;

        $temp = $items;

        $isSingle = isset($items['ID']); // если массив деталки, а не списка
        if ($isSingle) $temp = [$items];

        $ids = DataHelper::pluckColumn($temp, 'PROPERTIES.' . $propCode . '.VALUE');
        if (!$ids) return;

        $prop = reset($temp)['PROPERTIES'][$propCode];
        if ($prop['PROPERTY_TYPE'] !== 'E') return;

        $iblockID = $prop['LINK_IBLOCK_ID'];
        $itemByID = IblockManager::getListWithProps(
            filter: ['ID' => $ids, 'IBLOCK_ID' => $iblockID, 'ACTIVE' => 'Y'],
            select: $select['fields'] ?: [],
            propCodes: $select['propCodes'],
            propFields: $select['propFields'] ?: []
        );
        if (!$itemByID) return;

        $isMultiple = $prop['MULTIPLE'] == 'Y';
        foreach ($temp as &$item) {

            $item['PROPS'][$propCode] = [];
            if ($isMultiple) {
                foreach ($item['PROPERTIES'][$propCode]['VALUE'] as $id) {

                    if (!$itemByID[$id]) {// если нет значит элемент не найден/не активен
                        continue;
                    }
                    $item['PROPS'][$propCode][] = $itemByID[$id];
                }
            } else {
                $id = $item['PROPERTIES'][$propCode]['VALUE'];
                if (!$itemByID[$id]) {
                    continue;
                }
                $item['PROPS'][$propCode] = $itemByID[$id];
            }
        }

        if ($isSingle) $temp = $temp[0];

        $items = $temp;
    }
}
