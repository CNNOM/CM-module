<?php
declare(strict_types=1);

namespace Cloudmill\App\Services;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Cookie;

final class UserListCookieStorage
{
    private const TTL = 2592000;

    private string $cookieName;
    private array $items = [];
    private array $changes = [
        'added' => [],
        'removed' => [],
    ];
    private int $expiration;

    public function __construct(string $cookieName, ?int $expiration = null)
    {
        $this->cookieName = $cookieName;
        $this->expiration = $expiration ?? (time() + self::TTL);
        $this->items = $this->read();
    }

    public function get(): array
    {
        return $this->items;
    }

    public function addItems(array $ids): bool
    {
        $before = $this->items;
        $this->items = $this->normalize(array_merge($this->items, $ids));
        $this->changes['added'] = array_values(array_diff($this->items, $before));

        if (!$this->changes['added']) {
            return false;
        }

        $this->persist();

        return true;
    }

    public function removeItems(array $ids): bool
    {
        $ids = $this->normalize($ids);
        $before = $this->items;
        $this->items = array_values(array_diff($this->items, $ids));
        $this->changes['removed'] = array_values(array_diff($before, $this->items));

        if (!$this->changes['removed']) {
            return false;
        }

        $this->persist();

        return true;
    }

    public function replace(array $ids): void
    {
        $before = $this->items;
        $this->items = $this->normalize($ids);
        $this->changes['added'] = array_values(array_diff($this->items, $before));
        $this->changes['removed'] = array_values(array_diff($before, $this->items));

        $this->persist();
    }

    public function clear(): void
    {
        $this->replace([]);
    }

    public function has(int $id): bool
    {
        return in_array($id, $this->items, true);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    private function read(): array
    {
        $request = Context::getCurrent()->getRequest();
        $rawValue = (string)$request->getCookie($this->cookieName);

        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->normalize($decoded);
        }

        return $this->normalize(explode(',', $rawValue));
    }

    private function normalize(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    private function persist(): void
    {
        $cookie = new Cookie(
            $this->cookieName,
            json_encode($this->items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            $this->expiration
        );
        $cookie->setPath('/');
        $cookie->setHttpOnly(false);

        Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
        $_COOKIE[$this->cookieName] = (string)$cookie->getValue();
    }
}
