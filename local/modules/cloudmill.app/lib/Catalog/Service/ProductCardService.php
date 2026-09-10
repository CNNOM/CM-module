<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class ProductCardService
{
    /** Карточки активных товаров, индексированные по ID. */
    public static function findByIds(array $ids): array
    {
        $items = self::load($ids);

        foreach ($items as &$item) {
            $gallery = (array)($item['PROPERTIES']['GALLERY']['VALUE'] ?? []);
            $imageId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE'] ?: reset($gallery));
            $image = $imageId > 0
                ? \CFile::ResizeImageGet($imageId, ['width' => 100, 'height' => 100])
                : false;

            $item = [
                'id' => (int)$item['ID'],
                'name' => (string)$item['NAME'],
                'image' => (string)($image['src'] ?? ''),
                'url' => (string)$item['DETAIL_PAGE_URL'],
            ];
        }
        unset($item);

        return $items;
    }

    public static function getSectionId(int $id): int
    {
        return (int)(self::load([$id], activeOnly: false)[$id]['IBLOCK_SECTION_ID'] ?? 0);
    }

    private static function load(array $ids, bool $activeOnly = true): array
    {
        if (!$ids || !Loader::includeModule('iblock')) {
            return [];
        }

        $iblockId = IblockManager::getID(CatalogService::IBLOCK_CODE);
        if ($iblockId <= 0) {
            return [];
        }

        return IblockManager::getList(
            filter: ['IBLOCK_ID' => $iblockId, 'ID' => $ids, ...($activeOnly ? ['ACTIVE' => 'Y'] : [])],
            select: ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
            preferByID: true,
        );
    }
}
