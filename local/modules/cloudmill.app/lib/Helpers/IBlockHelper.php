<?php

declare(strict_types=1);

namespace Cloudmill\App\Helpers;

use Bitrix\Iblock\Component\Tools;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use CIBlockElement;
use CIBlockSection;
use CIBlockType;
use Cloudmill\App\ExceptionHandlers\AppExceptionHandler;

final class IBlockHelper
{
    private static ?array $cachedIDByCode = null;

    public static function getID(string $code, bool $resetCache = false): int
    {
        if (!$code) return 0;

        if ($resetCache) {
            self::$cachedIDByCode = null;
        }

        if (isset(self::$cachedIDByCode)) {
            return self::$cachedIDByCode[$code] ?? 0;
        }

        try {
            self::$cachedIDByCode = [];

            if (!Loader::includeModule('iblock')) {
                return 0;
            }

            $res = IblockTable::getList(['select' => ['ID', 'CODE']]);

            while ($item = $res->fetch()) {
                self::$cachedIDByCode[$item['CODE']] = (int)$item['ID'];
            }

            return self::$cachedIDByCode[$code] ?? 0;
        } catch (SystemException|LoaderException $e) {
            AppExceptionHandler::handle($e);
            return 0;
        }
    }

    public static function getSectionIDByCode(string $code, string $iblockCode): int
    {
        if (empty($code)) {
            return 0;
        }

        try {
            if (!Loader::includeModule('iblock')) {
                return 0;
            }

            $section = SectionTable::getRow([
                'filter' => ['CODE' => $code, 'IBLOCK.CODE' => $iblockCode],
                'select' => ['ID']
            ]);

            return (int)($section['ID'] ?? 0);
        } catch (SystemException|LoaderException $e) {
            AppExceptionHandler::handle($e);
            return 0;
        }
    }

    public static function getSectionList($order = ["SORT" => "ASC"], $filter = [], $bIncCnt = false, $select = ['*', 'UF_*'], $nav = false, $preferBy = ''): array
    {
        if ($preferBy && !in_array($preferBy, $select)) {
            $select[] = $preferBy;
        }

        $data = [];

        $rs = \CIBlockSection::GetList(
            arOrder: $order,
            arFilter: $filter,
            bIncCnt: $bIncCnt,
            arSelect: $select,
            arNavStartParams: $nav
        );

        while ($item = $rs->GetNext()) {
            if ($preferBy) {
                $data[$item[$preferBy]] = $item;
            } else {
                $data[] = $item;
            }
        }

        return $data;
    }

    public static function getIBlockList($order = ["SORT" => "ASC"], $filter = [], $preferByID = false): array
    {
        $data = [];

        $rs = \CIBlock::GetList(
            arOrder: $order,
            arFilter: $filter,
        );
        while ($item = $rs->GetNext()) {
            if ($preferByID) {
                $data[$item['ID']] = $item;
            } else {
                $data[] = $item;
            }
        }

        return $data;
    }

    public static function getList(
        $order = ["SORT" => "ASC"], $filter = [], $nav = false, $select = [], $preferByID = false
    ): array
    {

        $data = [];

        $rs = \CIBlockElement::GetList(
            arOrder: $order,
            arFilter: $filter,
            arNavStartParams: $nav,
            arSelectFields: $select
        );
        while ($ar = $rs->GetNextElement()) {
            $item = $ar->GetFields();
            $item['PROPERTIES'] = $ar->GetProperties();
            if ($preferByID) {
                $data[$item['ID']] = $item;
            } else {
                $data[] = $item;
            }
        }

        return $data;
    }

    public static function getListWithProps(
        array $order = ["SORT" => "ASC"], $filter = [], $nav = false, $select = ['*'], $propCodes = [], $propFields = []
    ): array
    {
        $data = [];
        $rs = \CIBlockElement::GetList(
            arOrder: $order,
            arFilter: $filter,
            arNavStartParams: $nav,
            arSelectFields: $select
        );
        while ($ar = $rs->GetNext()) {
            $ar['PROPERTIES'] = [];
            $data[$ar['ID']] = $ar;
        }

        if (!$data || !$filter['IBLOCK_ID'] || !isset($propCodes)) return $data;

        \CIBlockElement::GetPropertyValuesArray(
            result: $data,
            iblockID: $filter['IBLOCK_ID'],
            filter: $filter,
            propertyFilter: ['CODE' => $propCodes ?: []],
            options: ['PROPERTY_FIELDS' => $propFields ?: []]
        );

        return $data;
    }

    public static function redirectToFirstSection(string $iblockCode, string $parentSectionCode = ''): void
    {
        $iblockId = self::getID($iblockCode);
        if (!$iblockId) {
            return;
        }

        $sectionId = false;
        if ($parentSectionCode) {
            $sectionId = self::getSectionIDByCode($parentSectionCode, $iblockCode);
        }

        $section = CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                'SECTION_ID' => $sectionId,
                'ACTIVE' => 'Y',
            ],
            true,
            ['ID', 'NAME', 'CODE', 'SECTION_PAGE_URL', 'ELEMENT_CNT'],
            ['nTopCount' => 1]
        )->GetNext();

        if ($section && !empty($section['SECTION_PAGE_URL'])) {
            LocalRedirect($section['SECTION_PAGE_URL']);
        }

        Tools::process404(showPage: true);
    }

    public static function redirectToFirstElement(string $iblockCode, string $sectionCode = ''): void
    {
        if ($url = self::getFirstElementUrl($iblockCode, $sectionCode)) {
            LocalRedirect($url);
        }

        Tools::process404(showPage: true);
    }

    public static function getFirstElementUrl(string $iblockCode, string $sectionCode = ''): string | bool
    {
        $iblockId = self::getID($iblockCode);

        if (!$iblockId) {
            return false;
        }

        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
        ];

        if ($sectionCode !== '') {
            $sectionId = self::getSectionIDByCode($sectionCode, $iblockCode);

            if (!$sectionId) {
                return false;
            }

            $filter['SECTION_ID'] = $sectionId;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }

        $element = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => 1],
            ['ID', 'DETAIL_PAGE_URL']
        )->GetNext();

        if ($element && !empty($element['DETAIL_PAGE_URL'])) {
            return $element['DETAIL_PAGE_URL'];
        }

        return false;
    }

    public static function getPropsList($iblockID, $preferBy = false, $select = ['ID', 'CODE']): array
    {
        $data = [];
        if ($preferBy && !in_array($preferBy, $select)) {
            $select[] = $preferBy;
        }

        $res = PropertyTable::getList([
            'filter' => ['IBLOCK_ID' => $iblockID],
            'select' => $select,
        ]);

        while ($ar = $res->fetch()) {
            if ($preferBy) {
                $data[$ar[$preferBy]] = $ar;
            } else {
                $data[] = $ar;
            }
        }

        return $data;
    }

    public static function getIBlockType(string $code): array
    {
        return CIBlockType::GetList(arFilter: ['ID' => $code])->GetNext() ?: [];
    }

    public static function getEnumIDByValue(int|string $iblockID, string $propCode, string $propVal): array|bool
    {
        try {
            return PropertyTable::getList([
                'filter' => [
                    'IBLOCK_ID' => $iblockID,
                    '=CODE' => $propCode,
                    'ENUM.VALUE' => $propVal
                ],
                'select' => ['ENUM_ID' => 'ENUM.ID', 'ID' => 'ID', 'CODE' => 'CODE'],
                'runtime' => [
                    new Reference('ENUM', PropertyEnumerationTable::class, Join::on('this.ID', 'ref.PROPERTY_ID'))
                ]
            ])->fetch() ?: [];

        } catch (\Exception $e) {
            AppExceptionHandler::handle($e);
        }

        return false;
    }
}
