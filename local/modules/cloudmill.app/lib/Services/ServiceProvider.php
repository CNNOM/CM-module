<?php
declare(strict_types=1);

namespace CloudMill\App\Services;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Sale\Basket;
use Cloudmill\App\ExceptionHandlers\AppExceptionHandler;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Bitrix ServiceLocator wrapper
 */
final class ServiceProvider implements ContainerInterface
{
    const MODULE_PREFIX = 'cloudmill.';
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function YandexSmartCaptchaService(): ?YandexSmartCaptchaService
    {
        return self::getInstance()->get(YandexSmartCaptchaService::class);
    }

    public static function Basket(): ?Basket
    {
        return self::getInstance()->get(Basket::class);
    }

    public static function BasketService(): ?BasketService
    {
        return self::getInstance()->get(\Cloudmill\App\Services\BasketService::class);
    }

    /**
     * @template T
     * @param class-string<T>|string $id
     * @param string $prefix
     * @return T|null
     */
    public function get(string $id, string $prefix = self::MODULE_PREFIX): ?object
    {
        $locator = ServiceLocator::getInstance();
        if (!$locator->has($prefix . $id)) {
            AppExceptionHandler::addToLog("Сервис $id не найден");
            return null;
        }

        try {
            return $locator->get($prefix . $id);
        } catch (ObjectNotFoundException|NotFoundExceptionInterface $e) {
            AppExceptionHandler::handle($e);
            return null;
        }
    }

    public function has(string $id): bool
    {
        $locator = ServiceLocator::getInstance();
        return $locator->has($id);
    }
}
