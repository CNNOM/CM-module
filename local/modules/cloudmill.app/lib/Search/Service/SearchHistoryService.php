<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Service;

use CloudMill\App\Infrastructure\Storage\CookieListStorage;

final class SearchHistoryService
{
    public const LIMIT = 10;

    private const COOKIE_NAME = 'search_history';
    private const COOKIE_TTL = 60 * 60 * 24 * 90;

    private static ?CookieListStorage $storage = null;

    public static function getProductIds(): array
    {
        return self::storage()->get();
    }

    public static function addProduct(int $productId): void
    {
        self::storage()->prepend($productId);
    }

    public static function clear(): void
    {
        self::storage()->clear();
    }

    private static function storage(): CookieListStorage
    {
        return self::$storage ??= new CookieListStorage(
            self::COOKIE_NAME,
            self::COOKIE_TTL,
            self::LIMIT,
            httpOnly: true,
        );
    }
}
