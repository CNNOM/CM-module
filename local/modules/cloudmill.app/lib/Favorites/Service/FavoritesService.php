<?php

namespace CloudMill\App\Favorites\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use CloudMill\App\Infrastructure\Storage\CookieListStorage;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

class FavoritesService
{
    private const LIMIT = 8;
    private const COOKIE_NAME = 'CATALOG_FAVORITE_LIST';
    private const COOKIE_TTL = 60 * 60 * 24 * 7;
    private const HL_SHARE = 'FavoritesShare';
    private const SHARE_TTL = '+7 days';

    private static ?CookieListStorage $storage = null;

    public static function add(int $productId): array
    {
        Loader::includeModule('iblock');

        if ($productId <= 0) {
            return [
                'success' => false,
                'code' => 'INVALID_PRODUCT',
            ];
        }

        if (self::has($productId)) {
            return ['success' => true, 'code' => 'EXISTS', 'count' => self::count()];
        }

        if (self::count() >= self::LIMIT) {
            return [
                'success' => false,
                'code' => 'LIMIT',
                'count' => self::count(),
            ];
        }

        $product = self::getProduct($productId);

        if (!$product) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
            ];
        }

        self::storage()->add($productId);

        return [
            'success' => true,
            'code' => 'ADDED',
            'count' => self::count(),
            'product' => $product,
        ];
    }

    public static function remove(int $productId): array
    {
        $sectionId = self::getProductSectionId($productId);

        self::storage()->remove($productId);

        return [
            'success' => true,
            'code' => 'REMOVED',
            'count' => self::count(),
            'sectionId' => $sectionId,
        ];
    }

    public static function clear(): array
    {
        self::storage()->clear();

        return [
            'success' => true,
            'code' => 'CLEARED',
            'count' => 0,
        ];
    }

    public static function share(): array
    {
        $items = self::getItems();

        if (empty($items)) {
            return [
                'success' => false,
                'code' => 'EMPTY_LIST',
                'error' => 'Favorites list is empty',
            ];
        }

        sort($items);
        $hash = md5(json_encode($items));

        $saved = HighloadBlockManager::getList(
            self::HL_SHARE,
            ['select' => ['ID', 'UF_HASH'], 'filter' => ['UF_HASH' => $hash], 'limit' => 1]
        )[0] ?? null;

        $entity = HighloadBlockManager::getEntity(self::HL_SHARE);
        if (!$entity) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND_HL',
                'error' => 'HL Share not found',
            ];
        }

        if ($saved !== null) {
            $result = $entity::update((int)$saved['ID'], [
                'UF_EXPIRED_DATE' => (new DateTime())->add(self::SHARE_TTL),
            ]);

            if (!$result->isSuccess()) {
                return [
                    'success' => false,
                    'code' => 'SAVE_ERROR',
                    'error' => implode('; ', $result->getErrorMessages()),
                ];
            }

            return [
                'success' => true,
                'code' => 'SHARED',
                'hash' => $saved['UF_HASH'],
                'result' => 'exist',
            ];
        }

        $result = $entity::add([
            'UF_HASH' => $hash,
            'UF_PRODUCTS' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'UF_EXPIRED_DATE' => (new DateTime())->add(self::SHARE_TTL),
        ]);

        if (!$result->isSuccess()) {
            return [
                'success' => false,
                'code' => 'SAVE_ERROR',
                'error' => implode('; ', $result->getErrorMessages()),
            ];
        }

        return [
            'success' => true,
            'code' => 'SHARED',
            'hash' => $hash,
            'result' => 'new',
        ];
    }

    public static function getSharedItems(string $hash): array
    {
        if ($hash === '') {
            return [];
        }

        $record = HighloadBlockManager::getList(
            self::HL_SHARE,
            ['filter' => ['UF_HASH' => $hash, '>UF_EXPIRED_DATE' => new DateTime()], 'limit' => 1]
        )[0] ?? null;

        if (!$record) {
            return [];
        }

        $items = json_decode((string)$record['UF_PRODUCTS'], true);

        return CookieListStorage::normalizeIds(is_array($items) ? $items : [], self::LIMIT);
    }

    public static function removeExpiredShares(): int
    {
        $expired = HighloadBlockManager::getList(
            self::HL_SHARE,
            ['select' => ['ID'], 'filter' => ['<=UF_EXPIRED_DATE' => new DateTime()]]
        );

        if (empty($expired)) {
            return 0;
        }

        $entity = HighloadBlockManager::getEntity(self::HL_SHARE);
        if (!$entity) {
            return 0;
        }

        $deleted = 0;
        foreach ($expired as $item) {
            $result = $entity::delete((int)$item['ID']);
            if ($result->isSuccess()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function has(int $productId): bool
    {
        return self::storage()->has($productId);
    }

    public static function count(): int
    {
        return self::storage()->count();
    }

    public static function getItems(): array
    {
        return self::storage()->get();
    }

    public static function getProducts(): array
    {
        $ids = self::getItems();

        if (empty($ids)) {
            return [];
        }

        $result = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => IblockManager::getID('catalog'),
                'ID' => $ids,
                'ACTIVE' => 'Y',
            ],
            false,
            false,
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PROPERTY_GALLERY']
        );

        while ($item = $rs->GetNext()) {
            $imagePath = '';
            $gallery = $item['PROPERTY_GALLERY_VALUE'] ?? [];
            if (!empty($gallery) && (int)$gallery[0] > 0) {
                $resized = \CFile::ResizeImageGet(
                    $gallery[0],
                    ['width' => 100, 'height' => 100],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                $imagePath = $resized['src'] ?? '';
            }

            $result[] = [
                'id'    => (int)$item['ID'],
                'name'  => $item['NAME'],
                'image' => $imagePath,
                'url'   => $item['DETAIL_PAGE_URL'],
            ];
        }

        return $result;
    }

    private static function getProduct(int $productId): ?array
    {
        $item = \CIBlockElement::GetList(
            [],
            [
                '=ID' => $productId,
                '=IBLOCK_ID' => IblockManager::getID('catalog'),
                '=ACTIVE' => 'Y',
            ],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PAGE_URL']
        )->GetNext();

        if (!$item) {
            return null;
        }

        $imagePath = '';
        if ((int)$item['PREVIEW_PICTURE'] > 0) {
            $imagePath = \CFile::ResizeImageGet((int)$item['PREVIEW_PICTURE'], ['width' => 100, 'height' => 100])['src'] ?? '';
        }

        return [
            'id'    => (int)$item['ID'],
            'name'  => $item['NAME'],
            'image' => $imagePath,
            'url'   => $item['DETAIL_PAGE_URL'],
        ];
    }

    private static function getProductSectionId(int $productId): int
    {
        $item = \CIBlockElement::GetList(
            [],
            [
                '=ID' => $productId,
                '=IBLOCK_ID' => IblockManager::getID('catalog'),
            ],
            false,
            ['nTopCount' => 1],
            ['ID', 'IBLOCK_SECTION_ID']
        )->GetNext();

        return $item ? (int)$item['IBLOCK_SECTION_ID'] : 0;
    }

    private static function storage(): CookieListStorage
    {
        return self::$storage ??= new CookieListStorage(self::COOKIE_NAME, self::COOKIE_TTL, self::LIMIT);
    }
}
