<?php

namespace Cloudmill\App\Repositories;

use Bitrix\Iblock\ElementTable;
use CFile;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockSection;
use CIBlockType;
use Cloudmill\App\Context\AppContext;
use CloudMill\App\Helpers\AppUtil;
use Cloudmill\App\Helpers\IBlockHelper;
use Exception;

/** Класс для быстрого тестового создания Типов ИБ, ИБ, свойств, элементов ИБ из кода в процессе разработки.
 * Предусмотрено только создание, если элемент есть - то firstOr* просто вернет существующий
 * В конце есть коммент с вызовом каждого метода, для копи-паста
 */
class IBlockSeeder
{
    public static array $result = [];

    public static function dumpResult(): void
    {
        dd(self::$result);
    }

    /**
     * @throws Exception
     */
    public static function firstOrCreateIBlock(
        string  $codeToFind,
        ?string $name = null,
        string  $listUrl = '',
        string  $sectionUrl = '',
        string  $detailUrl = '',
        string  $type = 'content',
        ?string $typeName = null,
        int     $sort = 500)
    {
        $typeName ??= $type;
        self::firstOrCreateIBlockType($type, $typeName);

        $code = $codeToFind;
        $name ??= $codeToFind;
        $iblock = IBlockHelper::getIBlockList(filter: ['CODE' => $code])[0] ?? null;

        if (!$iblock) {
            $iblock = self::createIBlock($name, $code, $listUrl, $sectionUrl, $detailUrl, $type, $sort);
            self::$result['iblock'] = ['created' => $iblock['CODE']];
        } else {
            self::$result['iblock'] = ['founded' => $iblock['CODE']];
        }

        return $iblock;
    }

    /**
     * @throws Exception
     */
    public static function firstOrCreateIBlockType(string $code, ?string $name = null, $fields = []): ?array
    {
        $item = IBlockHelper::getIBlockType($code);
        if ($item) {
            self::$result['iblock type'] = ['founded' => $item['ID']];
            return $item;
        }

        $name ??= $code;
        $arFields = [
            'ID' => $code,
            'SECTIONS' => $fields['SECTIONS'] ?? 'Y',
            'IN_RSS' => $fields['IN_RSS'] ?? 'N',
            'SORT' => $fields['SORT'] ?? 500,
            'LANG' => $fields['LANG'] ?? [
                    'ru' => ['NAME' => $name, 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы'],
                    'en' => ['NAME' => $name, 'SECTION_NAME' => 'Sections', 'ELEMENT_NAME' => 'Elements',]
                ]
        ];

        global $DB;
        $DB->StartTransaction();

        $ibt = new CIBlockType;
        if (!$ibt->Add($arFields)) {

            $DB->Rollback();
            throw new Exception("failed to add iblock type $code");
        }

        $DB->Commit();

        $item = IBlockHelper::getIBlockType($code);
        self::$result['iblock type'] = ['created' => $item['ID']];

        return $item;
    }

    /**
     * @throws Exception
     */
    private static function createIBlock(
        string $name, string $code, string $listUrl, string $sectionUrl, string $detailUrl, string $type, int $sort
    ): ?array
    {
        try {
            AppContext::getDB()->startTransaction();

            $arFields = [
                "NAME" => $name,
                "CODE" => $code,
                "LIST_PAGE_URL" => $listUrl,
                "SECTION_PAGE_URL" => $sectionUrl,
                "DETAIL_PAGE_URL" => $detailUrl,
                "IBLOCK_TYPE_ID" => $type,
                "SITE_ID" => ["s1"],
                "SORT" => $sort,
                "GROUP_ID" => ["2" => "R"] // чтение для всех пользователей
            ];

            $ib = new CIBlock;
            if (!$ib->Add($arFields)) {
                throw new Exception("Couldn't add iblock " . $ib->LAST_ERROR);
            }

            AppContext::getDB()->commitTransaction();
        } catch (Exception $e) {
            AppContext::getDB()->rollbackTransaction();
            throw $e;
        }

        IBlockHelper::getID($code, resetCache: true); // обновление кэша списка ИБ

        return IBlockHelper::getIBlockList(filter: ['CODE' => $code])[0];
    }

    /**
     * @throws Exception
     */
    public static function createIBlockPropsIfNotExists(int|string $iblockCode, ?array $propsList): void
    {
        if (!$propsList) {
            return;
        }

        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $errors = [];
        $propsInfo = self::getPropsInfo($iblockID);

        foreach ($propsList as $propCode => $propArr) {

            if ($propsInfo[$propCode]) {
                self::$result['props']['founded'][] = $propCode;
                continue;
            }

            $propArr['CODE'] = $propCode;
            $propArr['IBLOCK_ID'] = $iblockID;
            $propArr['SORT'] ??= 400; // чтобы было хорошо видно сгенерированные

            $ibp = new CIBlockProperty;
            if (!$ibp->Add($propArr)) {
                $errors[] = "Couldn't add iblock property $propCode:" . $ibp->LAST_ERROR;
                continue;
            }

            self::$result['props']['created'][] = $propCode;
        }

        if ($errors) {
            throw new Exception(implode("\n", $errors));
        }
    }

    /**
     * @throws Exception
     */
    public static function firstOrCreateElementsByMap($iblockCode, $itemsMap): array
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $data = [];
        foreach ($itemsMap as $sectionName => $itemList) {
            $section = [];
            $fields = [];

            if (!is_numeric($sectionName)) {
                $fields['NAME'] = $sectionName;
                $fields['IBLOCK_ID'] = $iblockID;
                $section = self::firstOrCreateSection($iblockCode, $fields);
            }

            foreach ($itemList as $item) {
                $sectID = $section['ID'];
                if ($sectID) {
                    $item['IBLOCK_SECTION_ID'] = $sectID;
                }

                $replicateCnt = 0;
                if (!empty($item['replicate'])) {
                    $replicateCnt = $item['replicate'];
                    unset($item['replicate']);
                }

                $newItem = self::firstOrCreateElement($iblockCode, $item);

                if ($replicateCnt) {
                    self::replicateItem($newItem['ID'], $replicateCnt);
                }

                $data[$sectID ?? 0][] = $newItem;
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public static function firstOrCreateSection($iblockCode, $fields = []): ?array
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $fields['CODE'] ??= AppUtil::translit($fields['NAME']);

        $section = IBlockHelper::getSectionList(filter: ['CODE' => $fields['CODE'], 'IBLOCK_ID' => $iblockID])[0];
        if (!$section) {
            $section = self::createSection($iblockCode, $fields);
            self::$result['sections']['created'][$section['ID']] = $fields['CODE'];
        } else {
            self::$result['sections']['founded'][$section['ID']] = $fields['CODE'];
        }

        return $section;
    }

    /**
     * @throws Exception
     */
    private static function createSection($iblockCode, $fields): array
    {
        try {
            AppContext::getDB()->startTransaction();

            $iblockID = IBlockHelper::getID($iblockCode);
            if (!$iblockID) {
                throw new Exception("iblock $iblockCode not found");
            }

            $ibs = new CIBlockSection;
            if (!$ibs->Add($fields)) {
                throw new Exception("Couldn't create section {$fields['CODE']}:" . $ibs->LAST_ERROR);
            }

            AppContext::getDB()->commitTransaction();
        } catch (Exception $e) {
            AppContext::getDB()->rollbackTransaction();
            throw $e;
        }

        return IBlockHelper::getSectionList(filter: ['CODE' => $fields['CODE'], 'IBLOCK_ID' => $iblockID])[0];
    }

    /**
     * Обновляет поля уже существующего раздела (ищет по NAME на первом уровне вложенности инфоблока).
     * @throws Exception
     */
    public static function updateSectionFieldsByName($iblockCode, string $sectionName, array $fields): ?array
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $section = IBlockHelper::getSectionList(
            filter: ['NAME' => $sectionName, 'IBLOCK_ID' => $iblockID, 'DEPTH_LEVEL' => 1]
        )[0] ?? null;

        if (!$section) {
            self::$result['sections']['not_found'][] = $sectionName;
            return null;
        }

        $ibs = new CIBlockSection;
        if (!$ibs->Update($section['ID'], $fields)) {
            throw new Exception("Couldn't update section {$sectionName}: " . $ibs->LAST_ERROR);
        }

        self::$result['sections']['updated'][$section['ID']] = $sectionName;

        return $section;
    }

    /**
     * Обновляет значения свойств уже существующего элемента (ищет по NAME в рамках указанного раздела).
     * @throws Exception
     */
    public static function updateElementPropsByName($iblockCode, int $sectionID, string $elementName, array $propertyValues): ?array
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $item = IBlockHelper::getList(
            filter: ['NAME' => $elementName, 'IBLOCK_ID' => $iblockID, 'SECTION_ID' => $sectionID]
        )[0] ?? null;

        if (!$item) {
            self::$result['items']['not_found'][] = $elementName;
            return null;
        }

        CIBlockElement::SetPropertyValuesEx($item['ID'], $iblockID, $propertyValues);
        self::$result['items']['updated'][$item['ID']] = $elementName;

        return $item;
    }

    /**
     * @throws Exception
     */
    public static function firstOrCreateElement($iblockCode, $fields): array
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $fields['CODE'] ??= AppUtil::translit($fields['NAME']);
        $fields['IBLOCK_ID'] ??= $iblockID;

        $item = IBlockHelper::getList(filter: ['CODE' => $fields['CODE'], 'IBLOCK_ID' => $iblockID])[0];
        $sectionID = $fields['IBLOCK_SECTION_ID'] ?? 0;

        if (!$item) {
            $item = self::createElement($iblockCode, $fields);
            self::$result['items'][$sectionID]['created'][$item['ID']] = $fields['CODE'];
        } else {
            self::$result['items'][$sectionID]['founded'][$item['ID']] = $fields['CODE'];
        }

        return $item;
    }

    /**
     * @throws Exception
     */
    private static function createElement($iblockCode, $fields)
    {
        try {
            AppContext::getDB()->startTransaction();

            $iblockID = IBlockHelper::getID($iblockCode);
            if (!$iblockID) {
                throw new Exception("iblock $iblockCode not found");
            }

            if ($fields['PROPERTY_VALUES']) {
                self::prepareElementPropsToSave($iblockCode, $fields['PROPERTY_VALUES']);
            }

            $ibe = new CIBlockElement;
            if (!$ibe->Add($fields)) {
                throw new Exception("Couldn't create section {$fields['CODE']}:" . $ibe->LAST_ERROR);
            }

            AppContext::getDB()->commitTransaction();
        } catch (Exception $e) {
            AppContext::getDB()->rollbackTransaction();
            throw $e;
        }

        return IBlockHelper::getList(filter: ['CODE' => $fields['CODE'], 'IBLOCK_ID' => $iblockID])[0];
    }

    /**
     * @throws Exception
     */
    private static function prepareElementPropsToSave($iblockCode, array &$props): void
    {
        $iblockID = IBlockHelper::getID($iblockCode);
        if (!$iblockID) {
            throw new Exception("iblock $iblockCode not found");
        }

        $propsInfo = self::getPropsInfo($iblockID);
        foreach ($props as $code => $val) {
            $props[$code] = self::formatPropByType($propsInfo[$code], $val);
        }

    }

    private static function formatPropByType($propInfo, $val): mixed
    {
        if (!$propInfo) {
            return $val;
        }

        switch ($propInfo['PROPERTY_TYPE']) {
            case 'S':

                if ($propInfo['MULTIPLE'] !== 'Y') {
                    return self::formatStrProp($propInfo, $val);
                }
                return array_map(fn($v) => self::formatStrProp($propInfo, $v), $val);

            case 'F':

                if ($propInfo['MULTIPLE'] !== 'Y') {
                    return is_array($val) ? $val : CFile::MakeFileArray($val);
                }
                return array_map(fn($v) => CFile::MakeFileArray($v), $val);

            case 'L':

                if ($propInfo['MULTIPLE'] !== 'Y') {
                    return IBlockHelper::getEnumIDByValue($propInfo['IBLOCK_ID'], $propInfo['CODE'], $val);
                }
                return array_map(fn($v) => IBlockHelper::getEnumIDByValue($propInfo['IBLOCK_ID'], $propInfo['CODE'], $v), $val);
        }

        return $val;
    }

    private static function formatStrProp(array $propInfo, $val): string|array
    {
        if (!$val) return '';

        if (is_string($val)) {
            return AppUtil::fixCopiedHtmlText($val);
        }

        if ($propInfo['WITH_DESCRIPTION'] === 'Y' && !empty($val['VALUE'])) {
            $val['VALUE'] = AppUtil::fixCopiedHtmlText($val['VALUE']);

            if (!empty($val['DESCRIPTION'])) {
                $val['DESCRIPTION'] = AppUtil::fixCopiedHtmlText($val['DESCRIPTION']);
            }
        }

        return $val;
    }

    private static function getPropsInfo(int|string $iblockID): array
    {
        return IBlockHelper::getPropsList($iblockID, preferBy: 'CODE', select: ['*']);
    }


    /**
     * @throws Exception
     */
    private static function replicateItem($elementID, $cnt): void
    {
        if (!$elementID || !$cnt) {
            return;
        }

        try {
            $iblockID = ElementTable::getList(
                ['filter' => ['ID' => $elementID], 'select' => ['IBLOCK_ID']]
            )->fetch()['IBLOCK_ID'];

            if (!$iblockID) {
                throw new Exception("iblock for element ID:$elementID not found");
            }

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $item = IBlockHelper::getListWithProps(filter: ['ID' => $elementID, 'IBLOCK_ID' => $iblockID])[$elementID];
        $iblockCode = IBlockHelper::getIBlockList(filter: ['ID' => $iblockID])[0]['CODE'] ?? '';

        if (!$item || !$iblockCode) {
            throw new Exception("empty item:{$item['ID']} or iblock code:{$iblockCode}");
        }

        $fields = [
            'NAME' => $item['NAME'],
            'IBLOCK_SECTION_ID' => $item['IBLOCK_SECTION_ID'],
            'IBLOCK_ID' => $item['IBLOCK_ID'],
            'PROPERTY_VALUES' => self::getElementPropValuesToSave($item['PROPERTIES'])
        ];

        foreach (range(1, $cnt) as $i) {

            $fieldsToSave = $fields;
            $suffix = ' | id:' . $elementID . ' section:' . $fields['IBLOCK_SECTION_ID'] . ' №' . $i;

            $fieldsToSave['NAME'] = $fields['NAME'] . $suffix;
            $fieldsToSave['CODE'] = AppUtil::translit($fieldsToSave['NAME']) . '-' . uniqid();

            $resItem = self::createElement($iblockCode, $fieldsToSave);
            self::$result['replicated'][$elementID][$resItem['ID']] = $resItem['CODE'];
        }

    }

    private static function getElementPropValuesToSave(mixed $props): array
    {
        $propsToSave = [];

        foreach ($props as $propCode => $propArr) {
            if (!$propArr['VALUE']) {
                continue;
            }

            if ($propArr['MULTIPLE'] === 'Y') {

                foreach ($propArr['VALUE'] as $key => $val) {

                    $propsToSave[$propCode][$key]['VALUE'] = $val['TEXT'] ?? $val;
                    if ($propArr['WITH_DESCRIPTION'] === 'Y') {
                        $propsToSave[$propCode][$key]['DESCRIPTION'] = $propArr['DESCRIPTION'][$key];
                    }
                }
            } else {

                if ($propArr['WITH_DESCRIPTION'] === 'Y') {
                    $propsToSave[$propCode]['VALUE'] = $propArr['VALUE']['TEXT'] ?? $propArr['VALUE'];
                    $propsToSave[$propCode]['DESCRIPTION'] = $propArr['DESCRIPTION'];
                } else {
                    $propsToSave[$propCode] = $propArr['VALUE'];
                }
            }
        }

        return $propsToSave;
    }

}

// хелпер для быстрого вызова
/*
$iblockCode = 'blog2';
$res = IBlockSeeder::firstOrCreateIBlock(
    $iblockCode,
    'Блог 2',
    '/blog/',
    '/blog/#SECTION_CODE#/',
    '/blog/#SECTION_CODE#/#CODE#/',
    'content2',
    'Контент2',
    501,
);

IBlockSeeder::createIBlockPropsIfNotExists(
    iblockCode: $iblockCode,
    propsList: [
        'PIC_JPG' => [
            'NAME' => 'Изображение',
            'MULTIPLE' => 'N',
            'PROPERTY_TYPE' => 'F',
            'FILE_TYPE' => 'webp,jpg,jpeg,png'
        ],
        'NAME' => [
            'NAME' => 'Название',
            'MULTIPLE' => 'N',
            'ROW_COUNT' => 3,
            'COL_COUNT' => 100,
            'PROPERTY_TYPE' => 'S',
        ],
        'DESC' => [
            'NAME' => 'Описание',
            'MULTIPLE' => 'Y', // default N
            "USER_TYPE" => "HTML",
            'PROPERTY_TYPE' => 'S', // default S
            "WITH_DESCRIPTION" => "Y",
            'MULTIPLE_CNT' => '2'
        ],
        'IS_NEW' => [
            'PROPERTY_TYPE' => 'L',
            'NAME' => 'Новинка',
            'LIST_TYPE' => 'C',// L- список, C - флажки
            'VALUES' => [
                ["VALUE" => "1", "DEF" => "N", "SORT" => "500"],
            ]
        ],
        'E_TYPE' => [
            'NAME' => 'Привязка',
            'MULTIPLE' => 'Y', // default N
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => 1,
            'MULTIPLE_CNT' => '2'
        ]
    ]
);

IBlockSeeder::firstOrCreateElementsByMap(
    iblockCode: $iblockCode,
    itemsMap: [
        'Раздел 1' => [
            [
                'replicate' => 3,
                'NAME' => 'Элемент 1 | Раздел 1',
                'PROPERTY_VALUES' => [
                    'PIC_JPG' => '/images/news-card-img.png',
                    'NAME' => '
                                Новинка!
                                Коллекция СORNER
                           ',
                    'E_TYPE' => 121,
                    'IS_NEW' => 1,
                    'DESC' => [
                        ['VALUE' => 'text1', 'DESCRIPTION' => 'desc1'],
                        ['VALUE' => 'text2', 'DESCRIPTION' => 'desc2'],
                        ['VALUE' => 'text3', 'DESCRIPTION' => 'desc3'],
                    ]
                ]
            ],
            [
                'NAME' => 'Элемент 2 | Раздел 1',
                'PROPERTY_VALUES' => [
                    'PIC_JPG' => '/images/news-card-img.png',
                    'NAME' => '
                Новинка!
                Коллекция СORNER
           ',
                    'E_TYPE' => 122,
                    'IS_NEW' => 1,
                    'DESC' => [
                        ['VALUE' => 'text1', 'DESCRIPTION' => 'desc1'],
                        ['VALUE' => 'text2', 'DESCRIPTION' => 'desc2'],
                        ['VALUE' => 'text3', 'DESCRIPTION' => 'desc3'],
                    ]
                ]
            ]
        ],
        'Раздел 2' => [
            [
                'NAME' => 'Элемент 1 | Раздел 2',
                'PROPERTY_VALUES' => [
                    'PIC_JPG' => '/images/news-card-img.png',
                    'NAME' => 'simple text 1',
                    'E_TYPE' => 121,
                    'DESC' => ['VALUE' => 'text1']
                ]
            ],
            [
                'NAME' => 'Элемент 2 | Раздел 2',
                'PROPERTY_VALUES' => [
                    'PIC_JPG' => '/images/news-card-img.png',
                    'NAME' => 'simple text 2',
                    'E_TYPE' => 122,
                    'DESC' => ['VALUE' => 'text2']
                ]
            ]
        ],

    ]
);
dd([IBlockSeeder::$result]);
die();
*/
