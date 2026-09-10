<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service\Abstract;

use CloudMill\App\Infrastructure\Storage\CookieListStorage;
use CloudMill\App\Infrastructure\Storage\SharedListStorage;

abstract class AbstractProductListService
{
    protected const INVALID_CODE = 'INVALID';

    /** Отдельное хранилище для каждого дочернего сервиса. */
    private static array $storages = [];

    public static function getItems(): array
    {
        return self::storage()->get();
    }

    public static function has(int $productId): bool
    {
        return self::storage()->has($productId);
    }

    public static function count(): int
    {
        return self::storage()->count();
    }

    public static function add(int $productId): array
    {
        if ($productId <= 0) {
            return static::errorResponse(static::INVALID_CODE);
        }

        if (static::has($productId)) {
            return ['success' => true, 'code' => 'EXISTS', 'count' => static::count()];
        }

        if (static::count() >= static::LIMIT) {
            return static::errorResponse('LIMIT');
        }

        $product = static::getProductData($productId);
        if (!$product) {
            return static::errorResponse('NOT_FOUND');
        }

        self::storage()->add($productId);

        return [
            'success' => true,
            'code' => 'ADDED',
            'count' => static::count(),
            'product' => $product,
        ];
    }

    public static function remove(int $productId): array
    {
        self::storage()->remove($productId);

        return ['success' => true, 'code' => 'REMOVED', 'count' => static::count()];
    }

    public static function clear(): array
    {
        self::storage()->clear();

        return ['success' => true, 'code' => 'CLEARED', 'count' => 0];
    }

    public static function share(): array
    {
        return self::sharedStorage()->share(static::getItems());
    }

    public static function getSharedItems(string $hash): array
    {
        return self::sharedStorage()->getItems($hash);
    }

    public static function removeExpiredShares(): int
    {
        return self::sharedStorage()->removeExpired();
    }

    abstract protected static function getProductData(int $productId): array;

    protected static function errorResponse(string $code): array
    {
        return ['success' => false, 'code' => $code, 'count' => static::count()];
    }

    private static function storage(): CookieListStorage
    {
        return self::$storages[static::class] ??= new CookieListStorage(
            static::COOKIE_NAME,
            static::COOKIE_TTL,
            static::LIMIT,
        );
    }

    private static function sharedStorage(): SharedListStorage
    {
        return new SharedListStorage(static::HL_SHARE, static::LIMIT);
    }
}
