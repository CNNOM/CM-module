<?php
declare(strict_types=1);

namespace CloudMill\App\Settings;

use Bitrix\Main\Config\Option;
use Cloudmill\App\ExceptionHandlers\AppExceptionHandler;

final class ModuleOption
{
    public const MODULE_NAME = 'Cloudmill.app';

    public static function get(string $key): mixed
    {
        $apiKeyProduct = Option::get(self::MODULE_NAME, $key);

        if (!$apiKeyProduct) {
            AppExceptionHandler::addToLog('Не найдены данные для ' . $key . ' в настройках модуля ' . self::MODULE_NAME);
            return null;
        }

        return $apiKeyProduct;
    }
}
