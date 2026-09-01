<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use CloudMill\App\Compare\Service\CatalogCompareService;
use CloudMill\App\Compare\Service\UserListStorage;

final class UserListManager
{
    public const ACTION_COMPARE = 'compare';
    public const ACTION_FAVORITE = 'favorite';
    public const ACTIONS = [
        self::ACTION_COMPARE,
        self::ACTION_FAVORITE,
    ];
    private static array $compareResolvedCache = [];

    public static function getStorage(string $action): UserListStorage
    {
        return new UserListStorage(self::getCookieName($action));
    }

    public static function getCookieName(string $action): string
    {
        return match ($action) {
            self::ACTION_COMPARE => 'compare',
            self::ACTION_FAVORITE => 'favorites',
            default => $action,
        };
    }

    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    public static function isInCompare(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        if (!isset(self::$compareResolvedCache[$id])) {
            self::$compareResolvedCache[$id] = (new CatalogCompareService())->normalizeCompareIds([$id]);
        }

        $storage = self::getStorage(self::ACTION_COMPARE);
        foreach (self::$compareResolvedCache[$id] as $compareId) {
            if ($storage->has((int)$compareId)) {
                return true;
            }
        }

        return false;
    }

    public static function getCompareButtonClass(int $id): string
    {
        return self::isInCompare($id) ? 'active' : '';
    }

    public static function getCompareButtonText(int $id): string
    {
        return self::isInCompare($id) ? 'Удалить из сравнения' : 'Добавить в сравнение';
    }

    public static function getButtonAttributes(string $action, int $id, array $params = []): string
    {
        $payloadData = $params ?: new \stdClass();
        $payload = htmlspecialcharsbx((string)json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return sprintf(
            'data-action="%s" data-action-items="%d" data-action-params=\'%s\'',
            htmlspecialcharsbx($action),
            $id,
            $payload
        );
    }
}
