<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Cookie;
use CFile;
use CloudMill\App\Infrastructure\Bitrix\Context\ApplicationContext;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

final class CompareService
{
    public const LIMIT = 8;

    private const COOKIE_NAME = 'compare';
    private const COOKIE_TTL = 60 * 60 * 6;
    private const HL_SHARE = 'CompareShare';
    private const SHARE_TTL = '+7 days';

    private static ?array $items = null;

    public static function getItems(): array
    {
        if (self::$items === null) {
            self::$items = self::readCookie();
        }

        return self::$items;
    }

    public static function has(int $id): bool
    {
        return in_array($id, self::getItems(), true);
    }

    public static function count(): int
    {
        return count(self::getItems());
    }

    public static function add(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'code' => 'INVALID', 'count' => self::count()];
        }

        if (self::has($id)) {
            return self::remove($id);
        }

        if (self::count() >= self::LIMIT) {
            return ['success' => false, 'code' => 'LIMIT', 'count' => self::count()];
        }

        $items = self::getItems();
        $items[] = $id;
        self::saveItems($items);

        return [
            'success' => true,
            'code' => 'ADDED',
            'count' => self::count(),
            'product' => self::getProductData($id),
        ];
    }

    public static function remove(int $id): array
    {
        $items = array_values(array_diff(self::getItems(), [$id]));
        self::saveItems($items);

        return ['success' => true, 'code' => 'REMOVED', 'count' => self::count()];
    }

    public static function clear(): array
    {
        self::saveItems([]);

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
            ['filter' => ['UF_HASH' => $hash], 'limit' => 1]
        )[0] ?? null;

        if (!$record) {
            return [];
        }

        $items = json_decode((string)$record['UF_PRODUCTS'], true);

        return self::normalize(is_array($items) ? $items : []);
    }

    public static function removeExpiredShares(): int
    {
        $expired = HighloadBlockManager::getList(
            self::HL_SHARE,
            ['select' => ['ID'], 'filter' => ['<UF_EXPIRED_DATE' => new DateTime()]]
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
        $items = IblockManager::getList(
            filter: [
                'IBLOCK_ID' => IblockManager::getID('catalog'),
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

    private static function readCookie(): array
    {
        $raw = (string)ApplicationContext::getRequest()->getCookie(self::COOKIE_NAME);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? self::normalize($decoded) : [];
    }

    private static function saveItems(array $items): void
    {
        self::$items = self::normalize($items);

        $cookie = new Cookie(
            self::COOKIE_NAME,
            json_encode(self::$items, JSON_UNESCAPED_UNICODE) ?: '[]',
            time() + self::COOKIE_TTL
        );
        $cookie->setPath('/');
        $cookie->setHttpOnly(false);

        ApplicationContext::getResponse()->addCookie($cookie);
    }

    private static function normalize(array $items): array
    {
        $items = array_values(array_unique(array_filter(
            array_map('intval', $items),
            static fn (int $id): bool => $id > 0
        )));

        return array_slice($items, 0, self::LIMIT);
    }
}
