<?php

declare(strict_types=1);

namespace CloudMill\App\Helpers;

final class LayerManager
{
    // base -----------------------------------------------------------------------------------------------
    /**
     * @param string $path SITE_TEMPLATE_PATH . "/include/layers/{$path}";
     * @param array $data массив вида ['layerVarName'=>'layerVarValue']
     * @return void
     */
    public static function include(string $path, array $data = []): void
    {
        global $APPLICATION;
        extract($data, EXTR_SKIP);
        self::formatPath($path);
        include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . "/include/layers/{$path}.php";
    }

    public static function includeMultiple(array $path): void
    {
        foreach ($path as $layer) {
            self::include($layer);
        }
    }

    /**
     * Вызов компонента включаемой области Main из файла. Возможен путь через точечную нотацию
     * @param string $path
     * @param bool $component
     * @return false|string|void
     */
    public static function area(string $path, bool $component = false)
    {
        global $APPLICATION;
        self::formatPath($path);

        if ($component) ob_start();
        $APPLICATION->IncludeComponent(
            "bitrix:main.include",
            "",
            array(
                "AREA_FILE_SHOW" => "file",
                "AREA_FILE_SUFFIX" => "inc",
                "EDIT_TEMPLATE" => "",
                "PATH" => SITE_TEMPLATE_PATH . '/include/areas/' . $path . '.php'
            )
        );
        if ($component) return ob_get_clean();
    }

    /**
     * Вызов компонента Main хлебных крошек
     * @param ?string $class
     * @return void
     */
    public static function breadcrumb(?string $class = '', ?string $template = "main"): void
    {
        global $APPLICATION;
        if ($class) {
            $APPLICATION->SetPageProperty('breadcrumb_class', $class);
        }

        $APPLICATION->IncludeComponent(
            "bitrix:breadcrumb",
            $template,
            array(
                "PATH" => "",
                "SITE_ID" => "s1",
                "START_FROM" => "0",
                "COMPONENT_TEMPLATE" => $template
            ),
            false
        );
    }

    public static function search(?string $view = 'desktop'): void
    {
        global $APPLICATION;

        $APPLICATION->IncludeComponent(
            "bitrix:search.form",
            "search",
            array(
                "VIEW" => $view,
                "PAGE" => "#SITE_DIR#search/index.php",
                "USE_SUGGEST" => "N"
            )
        );
    }

    private static function formatPath(string &$path): void
    {
        $path = str_replace('.php', '', $path);
        $path = str_replace('.', '/', $path);
    }

    // additional

    /**
     * @param $webp - Передается ID картинки
     * @param $jpg
     * @param $width
     * @param $height
     * @param string $alt
     * @param int $maxWidth
     * Если установлено, создаст source с meta аттрибутом, src не будет создано
     * тк ниже ожидается еще вызов этой функции для генерации десктоп версии с src
     * @param string $srcClass
     * @param string $srcAttr
     * @param bool $showDefault
     * @param int $resType - BX_RESIZE_IMAGE_EXACT by default
     * @param int $resMult
     * @return void
     */
    public static function picture($webp, $jpg, $width, $height, $alt = '', $maxWidth = 0, $srcClass = '', $srcAttr = '', bool $showDefault = true, $resType = 2, $resMult = 1): void
    {
        include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . "/include/layers/shared/picture.php";
    }

    public static function socialNetworks($color): void
    {
        include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . "/include/layers/shared/social_networks.php";
    }

    public static function banner(int $width = 3840, int $height = 1440, string $type = 'img', bool $parent = false): void
    {
        include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . "/include/layers/shared/banner.php";
    }
}
