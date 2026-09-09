<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use Bitrix\Main\Type\DateTime;
use CFile;
use CloudMill\App\Infrastructure\Storage\CookieListStorage;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

final class CompareService
{
    public const LIMIT = 8;

    private const COOKIE_NAME = 'compare';
    private const COOKIE_TTL = 60 * 60 * 6;
    private const HL_SHARE = 'CompareShare';
    private const SHARE_TTL = '+7 days';

    private static ?CookieListStorage $storage = null;

    public static function getItems(): array
    {
        return self::storage()->get();
    }

    public static function has(int $id): bool
    {
        return self::storage()->has($id);
    }

    public static function count(): int
    {
        return self::storage()->count();
    }

    public static function add(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'code' => 'INVALID', 'count' => self::count()];
        }

        if (self::has($id)) {
            return ['success' => true, 'code' => 'EXISTS', 'count' => self::count()];
        }

        if (self::count() >= self::LIMIT) {
            return ['success' => false, 'code' => 'LIMIT', 'count' => self::count()];
        }

        $product = self::getProductData($id);
        if (!$product) {
            return ['success' => false, 'code' => 'NOT_FOUND', 'count' => self::count()];
        }

        self::storage()->add($id);

        return [
            'success' => true,
            'code' => 'ADDED',
            'count' => self::count(),
            'product' => $product,
        ];
    }

    public static function remove(int $id): array
    {
        self::storage()->remove($id);

        return ['success' => true, 'code' => 'REMOVED', 'count' => self::count()];
    }

    public static function clear(): array
    {
        self::storage()->clear();

        return ['success' => true, 'code' => 'CLEARED', 'count' => 0];
    }

    public static function share(): array
    {
        $items = self::getItems();

        if (empty($items)) {
            return [
                'success' => false,
                'code' => 'EMPTY_LIST',
                'error' => 'Compare list is empty',
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

    private static function getProductData(int $id): array
    {
        $iblockId = IblockManager::getID('catalog');
        if ($iblockId <= 0) {
            return [];
        }

        $items = IblockManager::getList(
            filter: [
                'IBLOCK_ID' => $iblockId,
                'ID' => $id,
                'ACTIVE' => 'Y',
            ],
            select: ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
        );

        $item = $items[0] ?? null;
        if (!$item) {
            return [];
        }

        $imageId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE']);

        return [
            'id' => (int)$item['ID'],
            'name' => (string)$item['NAME'],
            'url' => (string)$item['DETAIL_PAGE_URL'],
            'image' => $imageId > 0 ? (string)CFile::GetPath($imageId) : '',
        ];
    }

    private static function storage(): CookieListStorage
    {
        return self::$storage ??= new CookieListStorage(self::COOKIE_NAME, self::COOKIE_TTL, self::LIMIT);
    }
}
