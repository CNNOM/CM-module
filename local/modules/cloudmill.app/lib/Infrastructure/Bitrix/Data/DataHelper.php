<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Data;

use Bitrix\Main\Grid\Declension;
use CFile;
use CUtil;

final class DataHelper
{
    public static function phoneHref($phone): string
    {
        return preg_replace('/\D+/', '', (string)$phone) ?? '';
    }

    public static function getFileInfo($fileID)
    {
        $file = CFile::GetFileArray($fileID);
        if (!$file) return [];

        $file['NAME'] = pathinfo($file['ORIGINAL_NAME'], PATHINFO_FILENAME);
        $file['EXT'] = pathinfo($file['ORIGINAL_NAME'], PATHINFO_EXTENSION);

        $desc = strtoupper($file['EXT']);
        if ($desc) $desc .= ', ';
        $desc .= CFile::FormatSize($file['FILE_SIZE']);

        $file['DESC'] = $desc;
        return $file;
    }

    public static function arrToJson($phpArr = []): string
    {
        if (!$phpArr) return '';
        return htmlspecialchars(json_encode($phpArr));
    }

    public static function declension($one, $four, $five, $num): string
    {
        $declension = new Declension(one: $one, four: $four, five: $five);
        return $declension->get($num);
    }

    public static function sortArrByColumn(?array &$arr, string|int $column): void
    {
        uasort($arr, function ($a, $b) use ($column) {
            return ($a[$column] ?? 0) - ($b[$column] ?? 0);
        });
    }

    /**
     * Возвращает массив значений из указанного пути с поддержкой вложенности (через точку).
     * Может возвращать ассоциативный массив, если указан ключ.
     *
     * @param array|null $array Исходный массив
     * @param string $valPath Путь к значению (например: 'PROPERTIES.PIC_JPG.VALUE')
     * @param string $keyPath Путь к ключу (например: 'ID'). Если пустой - возвращает обычный массив.
     * @return array
     */
    public static function pluckColumn(?array $array, string $valPath, string $keyPath = ''): array
    {
        if (empty($array)) return [];

        $result = [];
        $valKeys = explode('.', $valPath);
        $keyKeys = $keyPath !== '' ? explode('.', $keyPath) : [];

        foreach ($array as $item) {
            // get target val
            $value = $item;
            foreach ($valKeys as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }

            if ($value === null) {
                continue;
            }

            // get target key
            $keyValue = null;
            if ($keyKeys) {
                $keyValue = $item;
                foreach ($keyKeys as $key) {
                    if (!is_array($keyValue) || !array_key_exists($key, $keyValue)) {
                        $keyValue = null;
                        break;
                    }
                    $keyValue = $keyValue[$key];
                }
            }

            // res
            if ($keyValue !== null) {
                $result[$keyValue] = $value;
            } else {
                if (is_array($value)) { // множ свойства
                    $result = array_merge($result, $value);
                } else {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    public static function translit(string $str, ?array $params = null): string
    {
        $params ??= [
            "max_len" => "100", // обрезает символьный код до 100 символов
            "change_case" => "L", // буквы преобразуются к нижнему регистру
            "replace_space" => "-", // меняем пробелы на нижнее подчеркивание
            "replace_other" => "-", // меняем левые символы на нижнее подчеркивание
            "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
            "use_google" => "false", // отключаем использование google
        ];

        return CUtil::translit($str, "ru", $params);
    }

    public static function fixCopiedHtmlText(string $str): string
    {
        $str = trim($str);
        $lines = explode("\n", $str);

        $newLines = [];
        foreach ($lines as $line) {
            $newLines[] = ltrim($line);
        }

        return implode("\n", $newLines);
    }
}
