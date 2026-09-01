<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Service;

use Bitrix\Main\Web\Cookie;
use CloudMill\App\Infrastructure\Bitrix\Context\ApplicationContext;

final class SearchHistoryService
{
    public const LIMIT = 10;

    private const COOKIE_NAME = 'lenta_search_history';
    private const COOKIE_TTL = 60 * 60 * 24 * 90;

    private static ?array $items = null;

    public static function getProductIds(): array
    {
        return self::$items ??= self::readCookie();
    }

    public static function addProduct(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $items = array_values(array_diff(self::getProductIds(), [$productId]));
        array_unshift($items, $productId);

        self::saveItems($items);
    }

    public static function clear(): void
    {
        self::saveItems([]);
    }

    private static function readCookie(): array
    {
        $rawValue = (string)ApplicationContext::getRequest()->getCookie(self::COOKIE_NAME);
        if ($rawValue === '') {
            return [];
        }

        $items = json_decode($rawValue, true);

        return is_array($items) ? self::normalize($items) : [];
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
        $cookie->setHttpOnly(true);

        ApplicationContext::getResponse()->addCookie($cookie);
        $_COOKIE[self::COOKIE_NAME] = (string)$cookie->getValue();
    }

    private static function normalize(array $items): array
    {
        $items = array_values(array_unique(array_filter(
            array_map('intval', $items),
            static fn(int $id): bool => $id > 0
        )));

        return array_slice($items, 0, self::LIMIT);
    }
}
