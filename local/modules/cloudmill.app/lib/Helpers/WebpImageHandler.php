<?php
declare(strict_types=1);

namespace CloudMill\App\Helpers;

use CFile;
use Cloudmill\App\Context\AppContext;

final class WebpImageHandler
{
    public static function getWebpPath($fileID, $width, $height): string
    {
        if (!$fileID || !$width || !$height) {
            return '';
        }

        $picJpg = CFile::ResizeImageGet($fileID, ['width' => $width, 'height' => $height])['src'];
        return WebpImageHandler::createWebpImageLayer($picJpg, width: $width, height: $height);
    }

    const ALLOW_FILE_EXT = ['jpg', 'jpeg', 'png'];

    public static function createWebpImageLayer($filePath, $width, $height, $destDir = '/upload/webp_generated/', $quality = 80): string
    {
        if (!$filePath || !$width || !$height) {
            return '';
        }

        $filePathAbs = $_SERVER["DOCUMENT_ROOT"] . $filePath;
        if (!file_exists($filePathAbs)) {
            return '';
        }

        $destDir = str_ends_with($destDir, '/') ? $destDir : $destDir . '/';
        $destDirAbs = $_SERVER["DOCUMENT_ROOT"] . $destDir . $width . '_' . $height . '/';

        if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0755, true)) {
            return '';
        }

        $pathInfo = pathinfo($filePathAbs);
        $fileExt = strtolower($pathInfo['extension'] ?? '');
        if (!in_array($fileExt, self::ALLOW_FILE_EXT)) {
            return '';
        }

        $webpPathAbs = $destDirAbs . $pathInfo['filename'] . '.webp';
        $webpPathRel = str_replace($_SERVER['DOCUMENT_ROOT'], '', $webpPathAbs);

        if (file_exists($webpPathAbs)) {
            return $webpPathRel;
        }

        AppContext::$sharedData['WEBP_GENERATED']++;

        return self::createWebpImage($filePathAbs, $webpPathAbs, $fileExt) ? $webpPathRel : '';
    }

    public static function createWebpFromPath($filePath, $destDir = '/upload/webp_generated/full/'): string
    {
        $filePathAbs = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        $destDir = str_ends_with($destDir, '/') ? $destDir : $destDir . '/';
        $destDirAbs = $_SERVER['DOCUMENT_ROOT'] . $destDir;
        if (!file_exists($filePathAbs)) {
            return '';
        }

        $pathInfo = pathinfo($filePathAbs);
        $fileExt = strtolower($pathInfo['extension'] ?? '');
        if (!in_array($fileExt, self::ALLOW_FILE_EXT)) {
            return '';
        }

        if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0755, true)) {
            return '';
        }

        $webpPathAbs = $destDirAbs . $pathInfo['filename'] . '.webp';
        $webpPathRel = str_replace($_SERVER['DOCUMENT_ROOT'], '', $webpPathAbs);
        if (file_exists($webpPathAbs)) {
            return $webpPathRel;
        }

        return self::createWebpImage($filePathAbs, $webpPathAbs, $fileExt) ? $webpPathRel : '';
    }

    private static function createWebpImage($filePathAbs, $destPathAbs, $fileExt, $quality = 80): bool
    {
        $image = match ($fileExt) {
            'jpg', 'jpeg' => imagecreatefromjpeg($filePathAbs),
            'png' => imagecreatefrompng($filePathAbs),
            default => null
        };
        if (!$image) {
            return false;
        }

        if ($fileExt === 'png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        $result = imagewebp($image, $destPathAbs, $quality);
        imagedestroy($image);

        return $result;
    }
}
