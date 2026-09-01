<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Settings;

use Bitrix\Main\Config\Option;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;

final class ModuleOption
{
    public const MODULE_NAME = 'CloudMill.app';

    public static function get(string $key): mixed
    {
        $apiKeyProduct = Option::get(self::MODULE_NAME, $key);

        if (!$apiKeyProduct) {
            ExceptionHandler::addToLog('Не найдены данные для ' . $key . ' в настройках модуля ' . self::MODULE_NAME);
            return null;
        }

        return $apiKeyProduct;
    }
}
