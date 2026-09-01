<?php

declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Presentation;

final class SvgRenderer
{
    public static function getPathByFileId(int $fileId): string
    {
        if ($fileId <= 0) {
            return '';
        }

        $svgPath = \CFile::GetPath($fileId);
        if (!$svgPath) {
            return '';
        }

        $svgAbsPath = $_SERVER['DOCUMENT_ROOT'] . $svgPath;
        if (!is_file($svgAbsPath)) {
            return '';
        }

        if (strtolower((string)pathinfo($svgAbsPath, PATHINFO_EXTENSION)) !== 'svg') {
            return '';
        }

        return $svgPath;
    }

    public static function getCodeByFileId(int $fileId): string
    {
        $svgPath = self::getPathByFileId($fileId);
        if ($svgPath === '') {
            return '';
        }

        $svgAbsPath = $_SERVER['DOCUMENT_ROOT'] . $svgPath;

        $svgCode = file_get_contents($svgAbsPath);
        if ($svgCode === false) {
            return '';
        }

        return $svgCode;
    }
}
