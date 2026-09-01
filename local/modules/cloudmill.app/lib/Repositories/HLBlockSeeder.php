<?php

namespace Cloudmill\App\Repositories;

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\DB\SqlQueryException;
use CFile;
use Cloudmill\App\Context\AppContext;
use CloudMill\App\Helpers\HLBlockHelper;
use CUserFieldEnum;
use CUserTypeEntity;
use Exception;

/**
 * Класс для быстрого тестового создания ХЛ-блоков
 *  В конце есть коммент с вызовом каждого метода, для копи-паста
 */
class HLBlockSeeder
{
    private static array $result = [];

    public static function dumpResult(): void
    {
        dd(self::$result);
    }

    /**
     * Создает Highload-блок, если он еще не существует в системе.
     *
     * @param string $name Название сущности (Entity Name). напр. "MyTable".
     * @param string $tableName Имя таблицы в БД. напр. "my_table_name".
     * @param string $displayName Имя таблицы в админке.
     *
     * @return int|bool Возвращает ID созданного или найденного HL-блока, либо false при ошибке.
     */
    public static function createHLBlock(string $name, string $tableName, string $displayName = ''): array|bool|int
    {
        return self::firstOrCreateHLBlock($name, $tableName, $displayName);
    }

    /**
     * @throws Exception
     */
    private static function firstOrCreateHLBlock(string $name, string $tableName, $displayName = ''): array
    {
        $hlBlock = HLBlockHelper::getInfo(filter: ['NAME' => $name])[0];

        if ($hlBlock) {
            self::$result['hlBlock'] = ['founded' => $hlBlock];
            return $hlBlock;
        }

        $hlBlock = self::createHLBlockRaw($name, $tableName, $displayName);
        self::$result['hlBlock'] = ['created' => $hlBlock];

        return $hlBlock;
    }

    /**
     * @throws Exception
     */
    private static function createHLBlockRaw($name, $tableName, $displayName = ''): array
    {
        try {
            AppContext::getDB()->startTransaction();

            $result = HighloadBlockTable::add([
                'NAME' => $name,
                'TABLE_NAME' => $tableName,
            ]);

            if (!$result->isSuccess()) {
                $errors = $result->getErrorMessages();
                throw new Exception("Ошибка создания ХЛ-блока $name: " . implode(", ", $errors));
            }

            $hlId = $result->getId();
            if ($displayName) {
                $res = self::setHLBlockDisplayName($displayName, $hlId);
            }

            AppContext::getDB()->commitTransaction();
        } catch (Exception $e) {
            AppContext::getDB()->rollbackTransaction();
            throw $e;
        }

        $hlBlock = HLBlockHelper::getInfo(filter: ['ID' => $result->getId()])[0];

        if (!$hlBlock) {
            throw new Exception("Созданный ХЛ-блок $name не найден");
        }

        return $hlBlock;
    }

    /**
     * @throws Exception
     */
    private static function setHLBlockDisplayName(mixed $displayName, int $hlId): bool
    {
        $res = HighloadBlockLangTable::add([
            'ID' => $hlId,
            'LID' => 'ru',
            'NAME' => $displayName
        ]);

        if (!$res->isSuccess()) {
            throw new Exception("Can't set hlblock display name:" . implode(', ', $res->getErrorMessages()));
        }

        return true;
    }

    /**
     * @throws Exception
     */
    private static function createFieldsIfNotExists(string $hlCode, ?array $fields): void
    {
        if (!$fields) {
            return;
        }

        $errors = [];
        $propsInfo = HLBlockHelper::getFieldsInfo($hlCode);

        foreach ($fields as $propCode => $propArr) {

            if ($propsInfo[$propCode]) {
                self::$result['props']['founded'][] = $propCode;
                continue;
            }

            $propArr['FIELD_NAME'] = $propCode;
            if (!self::createFieldRaw($hlCode, $propArr)) {
                $errors[] = "Couldn't add field $propCode";
                continue;
            }

            self::$result['props']['created'][] = $propCode;
        }

        if ($errors) {
            throw new Exception(implode("\n", $errors));
        }
    }

    /**
     * Массив arFields:
     * ENTITY_ID - сущность
     * FIELD_NAME - фактически имя столбца в БД в котором будут храниться значения свойства.
     * USER_TYPE_ID - тип свойства
     * XML_ID - идентификатор для использования при импорте/ экспорте
     * SORT - порядок сортировки (по умолчанию 100)
     * MULTIPLE - признак множественности Y/ N (по умолчанию N)
     * MANDATORY - признак обязательности ввода значения Y/ N (по умолчанию N)
     * SHOW_FILTER - показывать или нет в фильтре админ листа и какой тип использовать. см. ниже.
     * SHOW_IN_LIST - показывать или нет в админ листе (по умолчанию Y)
     * EDIT_IN_LIST - разрешать редактирование в формах, но не в API! (по умолчанию Y)
     * IS_SEARCHABLE - поле участвует в поиске (по умолчанию N)
     * SETTINGS - массив с настройками свойства зависимыми от типа свойства. Проходят "очистку" через обработчик типа PrepareSettings.
     * EDIT_FORM_LABEL - массив языковых сообщений вида array("ru"=>"привет", "en"=>"hello")
     * LIST_COLUMN_LABEL
     * LIST_FILTER_LABEL
     * ERROR_MESSAGE
     * HELP_MESSAGE
     *
     * В случае ошибки ловите исключение приложения!
     *
     * Значения для SHOW_FILTER:
     * N - не показывать
     * I - точное совпадение
     * E - маска
     * S - подстрок
     *
     * @param string $hlCode
     * @param array $field
     * @return int
     * @throws SqlQueryException
     */
    private static function createFieldRaw(string $hlCode, array $field): int
    {
        $entityId = HLBlockHelper::getEntityIDByCode($hlCode);
        if (!$entityId) {
            return false;
        }

        try {
            AppContext::getDB()->startTransaction();

            $settings = self::getSettingsByUFieldType($field['USER_TYPE_ID'], $field['SETTINGS']);

            $arFields = [
                "FIELD_NAME" => $field['FIELD_NAME'],
                "USER_TYPE_ID" => $field['USER_TYPE_ID'] ?? "string",
                "MULTIPLE" => $field['MULTIPLE'] ?? "N",
                "SORT" => $field['SORT'] ?? 100,
                "XML_ID" => $field['FIELD_NAME'],
                "ENTITY_ID" => $entityId,
                "EDIT_FORM_LABEL" => $field['EDIT_FORM_LABEL'],
                "LIST_COLUMN_LABEL" => $field['EDIT_FORM_LABEL'],
                "LIST_FILTER_LABEL" => $field['EDIT_FORM_LABEL'],
                "SETTINGS" => $settings
            ];

            $obUserField = new CUserTypeEntity();
            $fieldId = $obUserField->Add($arFields);
            if (!$fieldId) {
                throw new Exception("Couldn't add field $hlCode");
            }

            // создание элементов списка
            if ($field['USER_TYPE_ID'] === 'enumeration' && !empty($field['LIST_VALUES'])) {
                $addRes = self::addFieldEnums($fieldId, $field['LIST_VALUES']);
                if (!$addRes) {
                    throw new Exception("Couldn't add field values for $hlCode");
                }
                self::$result['propEnums']['created'][$field['FIELD_NAME']] = $field['LIST_VALUES'];
            }

            AppContext::getDB()->commitTransaction();
        } catch (Exception $e) {
            AppContext::getDB()->rollbackTransaction();
            throw $e;
        }

        return $fieldId;
    }

    private static function getSettingsByUFieldType(string $UFieldType, $field): array
    {
        return match ($UFieldType) {
            'string' => [
                "DEFAULT_VALUE" => "",
                "SIZE" => "100",
                "ROWS" => "1"
            ],
            'file' => [
                "EXTENSIONS" => [],
                "TARGET_BLANK" => "N",
                "LIST_WIDTH" => 100,
                "LIST_HEIGHT" => 100,
            ],
            'iblock_element' => [
                "IBLOCK_ID" => $field['IBLOCK_ID'] ?? 0,
                "ACTIVE_FILTER" => "Y"
            ],
            'enumeration' => [
                "DISPLAY" => "CHECKBOX",// CHECKBOX, LIST
                "LIST_HEIGHT" => 5
            ],
            'datetime' => [
                "DEFAULT_VALUE" => ['TYPE' => 'NOW', 'VALUE' => null],
            ],
            default => [],
        };

    }

    private static function addFieldEnums(int $fieldId, $values): bool
    {
        $obEnum = new CUserFieldEnum();
        $toSave = [];
        foreach ($values as $i => $val) {
            $toSave['n' . $i] = [
                'VALUE' => $val['VALUE'],
                'DEF' => $val['DEF'] ?? 'N',
                'SORT' => ($i + 1) * 10,
                'XML_ID' => $val['XML_ID'] ?: 'XML_' . $i
            ];
        }

        return $obEnum->SetEnumValues($fieldId, $toSave);
    }

    /**
     * Создает пользовательское поле в Highload-блоке, если оно еще не существует.
     * Автоматически обрабатывает списки (enumeration) и привязки к инфоблокам.
     *
     * @param string $hlCode Символьный код HL-блока (например, 'ColorReference').
     * @param string $type Тип поля (string, datetime, enumeration, iblock_element, file). Остальные - не тестил
     * @param string $code Код поля с префиксом UF_ (например, 'UF_BRAND').
     * @param string $name Название поля для интерфейса (RU/EN).
     * @param bool $multiple Множественное или нет.
     * @param array $listValues Значения для типа 'enumeration'. ключ "DEF" для дефолтного. Формат: ["V1", "DEF" => "V2", "V3"].
     * @param int $iblockLinkID ID инфоблока (используется, если $type = 'iblock_element').
     *
     * @return void
     */
    public static function createField(
        string $hlCode,
        string $type,
        string $code,
        string $name,
        bool   $multiple = false,
        array  $listValues = [],
        int    $iblockLinkID = 0
    ): void
    {
        $enums = [];
        foreach ($listValues as $key => $val) {
            $enums[] = [
                'VALUE' => $val,
                'DEF' => ($key === 'DEF' ? 'Y' : 'N'),
            ];
        }

        $fields = [
            $code => [
                "USER_TYPE_ID" => $type,
                "EDIT_FORM_LABEL" => ['ru' => $name, 'en' => $name],
                "MULTIPLE" => $multiple ? "Y" : "N",
                "LIST_VALUES" => $enums,
                "SETTINGS" => $iblockLinkID ? ["IBLOCK_ID" => $iblockLinkID] : [],
            ]
        ];

        self::createFieldsIfNotExists($hlCode, $fields);
    }

    /**
     * @param string $hlCode
     * @param array $values
     * @param $formatValues - если true, то значения списка и пути к файлу будут автоматически форматированы для сохранения
     * @return array
     * @throws Exception
     */
    public static function addElement(string $hlCode, array $values, $formatValues = true): array
    {
        $hl = HLBlockHelper::getEntity($hlCode);
        if (!$hl) {
            throw new Exception("Couldn't get entity $hlCode");
        }

        if ($formatValues) {
            self::formatValuesToSave($hlCode, $values);
        }

        $res = $hl::add($values);

        if (!$res->isSuccess()) {
            throw new Exception("Couldn't add item $hlCode:" . implode(', ', $res->getErrorMessages()));
        }

        return $res->getData();
    }

    private static function formatValuesToSave(string $hlCode, array &$values)
    {
        $fieldsInfo = HLBlockHelper::getFieldsInfo($hlCode);

        foreach ($values as $key => $value) {
            $field = $fieldsInfo[$key] ?? [];
            if (!$field) continue;

            switch ($field['USER_TYPE_ID']) {
                case 'enumeration':

                    if ($field['MULTIPLE'] !== 'Y') {
                        if (is_array($value)) break;
                        $values[$key] = HLBlockHelper::getEnumList($key, $value)[0]['ID'] ?? '';
                        break;
                    }

                    $value = is_array($value) ? $value : [$value];
                    $enums = HLBlockHelper::getEnumList($key, $value, 'VALUE');

                    $values[$key] = array_map(fn($v) => $enums[$v]['ID'] ?? '', $value);
                    break;
                case 'file':

                    if ($field['MULTIPLE'] !== 'Y') {
                        if (is_array($value)) break;

                        $values[$key] = CFile::MakeFileArray($value);
                        break;
                    }

                    $value = is_array($value) ? $value : [$value];
                    $values[$key] = array_map(fn($v) => CFile::MakeFileArray($v), $value);
                    break;
                default:
            }
        }
    }
}

/*
 $name = 'Test2';
HLBlockSeeder::createHLBlock($name, 'test2', 'Тест2');
HLBlockSeeder::createField(hlCode: $name, type: 'datetime', code: 'UF_DATE', name: 'Дата создания');
HLBlockSeeder::createField(hlCode: $name, type: 'string', code: 'UF_NAME', name: 'Название');
HLBlockSeeder::createField(hlCode: $name, type: 'file', code: 'UF_FILE', name: 'Файл', multiple: true);
HLBlockSeeder::createField(hlCode: $name, type: 'iblock_element', code: 'UF_IBLOCK', name: 'Привязка', multiple: true, iblockLinkID: 1);
HLBlockSeeder::createField(hlCode: $name, type: 'enumeration', code: 'UF_LIST', name: 'Список', multiple: true, listValues: [
    "Значение1", "DEF" => "Значение2", "Значение3"
]);

$res = HLBlockSeeder::addElement($name, [
    'UF_NAME' => 'Test name',
    'UF_FILE' => ['/local/templates/main/images/about-banner.jpg', '/local/templates/main/images/hero-bg.png'],
    'UF_IBLOCK' => [10, 12],
    'UF_LIST' => ["Значение3", "Значение1"]
]);
*/