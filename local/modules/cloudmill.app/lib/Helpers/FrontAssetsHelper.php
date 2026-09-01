<?php
declare(strict_types=1);

namespace CloudMill\App\Helpers;

use Bitrix\Main\Page\Asset;

/* Класс чисто для дебага фронтовского сброда файлов */
final class FrontAssetsHelper
{
    private const JS_DIR = '/js/front';
    private const CSS_DIR = '/styles/front';

    /* подключит все файлы */
    public static function includeAll(): void
    {
        self::includeFiles(self::scanDir(self::JS_DIR), self::scanDir(self::CSS_DIR));
    }

    /* подключит все перечисленные */
    public static function includeOnly(array $fileNames): void
    {
        self::includeFiles(
            array_intersect(self::scanDir(self::JS_DIR), $fileNames),
            array_intersect(self::scanDir(self::CSS_DIR), $fileNames)
        );
    }

    /* подключит все кроме перечисленных */
    public static function includeAllExcept(array $fileNames): void
    {
        self::includeFiles(
            array_diff(self::scanDir(self::JS_DIR), $fileNames),
            array_diff(self::scanDir(self::CSS_DIR), $fileNames)
        );
    }

    private static function scanDir(string $relativeDir): array
    {
        $files = scandir($_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . $relativeDir);
        return array_values(array_diff($files, ['.', '..']));
    }

    private static function includeFiles(array $jsFiles, array $cssFiles): void
    {
        $asset = Asset::getInstance();

        foreach ($jsFiles as $file) {
            $asset->addString('<script type="module" src="' . SITE_TEMPLATE_PATH . self::JS_DIR . '/' . $file . '"></script>');
        }

        foreach ($cssFiles as $file) {
            $asset->addCss(SITE_TEMPLATE_PATH . self::CSS_DIR . '/' . $file, true);
        }
    }
}
