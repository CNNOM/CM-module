<?php

namespace Sprint\Migration;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use RuntimeException;

class CreateSharedListsHlBlocks20260909170000 extends Version
{
    protected $description = 'HL-блоки для общих списков избранного и сравнения';

    public function up()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new RuntimeException('Не установлен модуль highloadblock');
        }

        $helper = $this->getHelperManager()->Hlblock();

        foreach ([
            'FavoritesShare' => ['favorites_share', 'Общие списки избранного'],
            'CompareShare' => ['compare_share', 'Общие списки сравнения'],
        ] as $name => [$table, $label]) {
            $existing = HighloadBlockTable::getList([
                'filter' => ['=NAME' => $name],
                'select' => ['ID'],
                'limit' => 1,
            ])->fetch();

            $id = $existing ? (int)$existing['ID'] : $helper->saveHlblock([
                'NAME' => $name,
                'TABLE_NAME' => $table,
                'LANG' => ['ru' => ['NAME' => $label]],
            ]);

            foreach ([
                'UF_HASH' => ['string', 'Хеш списка', ['SIZE' => 32, 'MAX_LENGTH' => 32]],
                'UF_PRODUCTS' => ['string', 'ID товаров (JSON)', ['ROWS' => 5, 'MAX_LENGTH' => 0]],
                'UF_EXPIRED_DATE' => ['datetime', 'Срок действия', []],
            ] as $code => [$type, $fieldLabel, $settings]) {
                $helper->saveField($id, [
                    'FIELD_NAME' => $code,
                    'USER_TYPE_ID' => $type,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'Y',
                    'SHOW_FILTER' => $code === 'UF_PRODUCTS' ? 'N' : 'I',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'N',
                    'IS_SEARCHABLE' => 'N',
                    'SETTINGS' => $settings,
                    'EDIT_FORM_LABEL' => ['ru' => $fieldLabel],
                    'LIST_COLUMN_LABEL' => ['ru' => $fieldLabel],
                    'LIST_FILTER_LABEL' => ['ru' => $fieldLabel],
                ]);
            }
        }
    }

    public function down()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new RuntimeException('Не установлен модуль highloadblock');
        }

        // Откат удаляет блоки вместе с сохранёнными общими списками.
        foreach (['CompareShare', 'FavoritesShare'] as $name) {
            $block = HighloadBlockTable::getList([
                'filter' => ['=NAME' => $name],
                'select' => ['ID'],
                'limit' => 1,
            ])->fetch();

            if (!$block) {
                continue;
            }

            $result = HighloadBlockTable::delete((int)$block['ID']);
            if (!$result->isSuccess()) {
                throw new RuntimeException(implode('; ', $result->getErrorMessages()));
            }
        }
    }
}
