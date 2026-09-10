<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Wrappers;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\SystemException;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;
use CUserTypeManager;

final class HighloadBlockManager
{
    /**
     * @param array|int|string $HLBlockCode - Could be a block, ID or NAME of block.
     * @return DataManager|bool|string|null
     */
    public static function getEntity(array|int|string $HLBlockCode): DataManager|bool|string|null
    {
        try {
            if (!Loader::includeModule('highloadblock')) return null;

            $hlBlock = $HLBlockCode;

            if (is_int($HLBlockCode) || ctype_digit((string)$HLBlockCode)) {
                $hlBlock = HighloadBlockTable::getById((int)$HLBlockCode)->fetch();
            } elseif (is_string($HLBlockCode)) {
                $hlBlock = HighloadBlockTable::getList([
                    'filter' => ['=NAME' => $HLBlockCode],
                    'limit' => 1,
                ])->fetch();
            }

            if (!$hlBlock || !is_array($hlBlock)) {
                return null;
            }

            $entity = HighloadBlockTable::compileEntity($hlBlock);
            return $entity->getDataClass();
        } catch (SystemException|LoaderException $e) {
            ExceptionHandler::handle($e);
        }
        return null;
    }

    public static function compileEntity(string $HLBlockCode): ?Entity
    {
        try {
            if (!Loader::includeModule('highloadblock')) return null;
            return HighloadBlockTable::compileEntity($HLBlockCode);
        } catch (SystemException|LoaderException $e) {
            ExceptionHandler::handle($e);
        }
        return null;
    }

    public static function getIDByName(string $name): ?string
    {
        if (!$name) return null;

        $block = self::getInfo(filter: ['=NAME' => $name], limit: 1)[0] ?? null;
        return $block ? (string)$block['ID'] : null;
    }

    public static function getInfo($order = ['ID' => 'ASC'], $filter = [], $select = ['*'], $limit = false, $preferBy = ''): array
    {
        try {
            if (!Loader::includeModule('highloadblock')) return [];

            $res = HighloadBlockTable::getList([
                'order' => $order,
                'select' => $select,
                'filter' => $filter,
                'limit' => $limit,
            ]);

            if (!$preferBy) return $res->fetchAll() ?: [];

            $items = [];
            while ($item = $res->fetch()) {
                $items[$item[$preferBy]][] = $item;
            }

            return $items;
        } catch (SystemException|LoaderException $e) {
            ExceptionHandler::handle($e);
            return [];
        }
    }

    public static function getList($hlCode, $params, $preferBy = ''): array
    {
        $hl = self::getEntity($hlCode);
        if (!$hl) return [];

        if (!empty($params['select']) && $preferBy) {
            $params['select'][] = $preferBy;
        }

        try {
            $res = $hl::getList($params);
        } catch (SystemException $e) {
            ExceptionHandler::handle($e);
            return [];
        }

        if (!$preferBy) return $res->fetchAll() ?: [];

        $items = [];
        while ($item = $res->fetch()) {
            $items[$item[$preferBy]] = $item;
        }

        return $items;
    }

    public static function getFieldsInfo($hlCode): array
    {
        /**  @global CUserTypeManager $USER_FIELD_MANAGER */
        global $USER_FIELD_MANAGER;

        return $USER_FIELD_MANAGER->GetUserFields(
            entity_id: HighloadBlockManager::getEntityIDByCode($hlCode),
            LANG: 'ru'
        );
    }

    public static function getEntityIDByCode(string $hlCode): string
    {
        $hlBlockId = self::getIDByName($hlCode);
        if (!$hlBlockId) {
            return '0';
        }

        return "HLBLOCK_" . $hlBlockId;
    }

    public static function getEnumList(string $fieldCode, array|string $value, $preferBy = ''): array
    {
        $value = is_array($value) ? $value : [$value];

        $res = \CUserFieldEnum::GetList(
            aFilter: ['USER_FIELD_NAME' => $fieldCode, 'VALUE' => $value],
        );

        $data = [];
        while ($item = $res->GetNext()) {
            if ($preferBy) {
                $data[$item[$preferBy]] = $item;
            } else {
                $data[] = $item;
            }
        }

        return $data;
    }
}
