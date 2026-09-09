<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Storage;

use Bitrix\Main\Web\Cookie;
use CloudMill\App\Infrastructure\Bitrix\Context\ApplicationContext;

final class CookieListStorage
{
    private array $items;

    public function __construct(
        private readonly string $cookieName,
        private readonly int $ttl,
        private readonly int $limit,
    ) {
        $raw = (string)ApplicationContext::getRequest()->getCookie($cookieName);
        $items = json_decode($raw, true);
        // Поддержка старых списков, записанных через запятую.
        $items = is_array($items) ? $items : explode(',', $raw);
        $this->items = self::normalizeIds($items, $limit);
    }

    public function get(): array
    {
        return $this->items;
    }

    public function has(int $id): bool
    {
        return in_array($id, $this->items, true);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function add(int $id): bool
    {
        if ($id <= 0 || $this->has($id) || $this->count() >= $this->limit) {
            return false;
        }

        $this->items[] = $id;
        $this->save();
        return true;
    }

    public function remove(int $id): void
    {
        $this->items = array_values(array_diff($this->items, [$id]));
        $this->save();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->save();
    }

    public static function normalizeIds(array $ids, int $limit): array
    {
        $ids = array_filter($ids, static fn(mixed $id): bool =>
            (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0
        );

        return array_slice(array_values(array_unique(array_map('intval', $ids))), 0, max(0, $limit));
    }

    private function save(): void
    {
        $cookie = new Cookie($this->cookieName, json_encode($this->items), time() + $this->ttl);
        $cookie->setPath('/');
        $cookie->setHttpOnly(false);
        ApplicationContext::getResponse()->addCookie($cookie);
    }
}
