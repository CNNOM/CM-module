<?php
declare(strict_types=1);

namespace CloudMill\App\Settings;

use Cloudmill\App\Helpers\IBlockHelper;

/**
 * Класс для работы со свойствами инфоблока Настройки
 */
final class PageSettings
{
    const EXCEPTION_MAIL_TO = 'test@yandex.com'; // почта для отправки текста ошибки
    private const IB_SETTINGS_CODE = 'page_settings';
    private static ?array $data = null;

    private static function init(): void
    {
        if (self::$data !== null) return;

        self::$data = [];
        $res = \CIBlockElement::GetList(
            arOrder: ['ID' => 'DESC'],
            arFilter: ['ACTIVE' => 'Y', 'IBLOCK_ID' => IBlockHelper::getID(self::IB_SETTINGS_CODE)],
            arNavStartParams: ['nTopCount' => 1],
        )->GetNextElement();
        if (!$res) return;

        foreach ($res->GetProperties() as $code => $arr) {
            self::$data[$code] = $arr['VALUE'];
            self::$data['~'][$code] = $arr['~VALUE'];
            self::$data['DESC'][$code] = $arr['DESCRIPTION'];
        }
    }

    public static function get(string $name, bool $convertToPath = false, bool $tildeVal = false)
    {
        self::init();

        if($tildeVal){
            return self::$data['~'][$name] ?? null;
        }

        if ($convertToPath) return \CFile::GetPath(self::$data[$name]);

        return self::$data[$name] ?? null;
    }

    public static function getDesc(string $name)
    {
        self::init();

        return self::$data['DESC'][$name] ?? null;
    }

    public static function getByPrefix(string $prefix = ''): array
    {
        self::init();

        foreach (self::$data as $code => $arr) {
            if (!str_starts_with($code, $prefix)) continue;
            $res[$code] = $arr;
        }

        return $res ?? [];
    }

    public static function getAll(): array
    {
        self::init();
        return self::$data;
    }

    public static function getDefaultPicture()
    {
        if (!isset(self::$data['DEFAULT_PICTURE_SRC'])) {
            self::$data['DEFAULT_PICTURE_SRC'] = self::get('DEFAULT_PICTURE', true) ?? '';
        }
        return self::$data['DEFAULT_PICTURE_SRC'];
    }
}
