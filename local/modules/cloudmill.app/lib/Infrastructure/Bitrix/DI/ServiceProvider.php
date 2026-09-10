<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\DI;

use CloudMill\App\Basket\Service\BasketService;
use CloudMill\App\Catalog\Service\CatalogService;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Validation\ValidationService;
use Bitrix\Sale\Basket;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;
use CloudMill\App\Integrations\Yandex\SmartCaptchaClient;
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

    public static function SmartCaptchaClient(): ?SmartCaptchaClient
    {
        return self::getInstance()->get(SmartCaptchaClient::class);
    }

    public static function Basket(): ?Basket
    {
        return self::getInstance()->get(Basket::class);
    }

    public static function BasketService(): ?BasketService
    {
        return self::getInstance()->get(BasketService::class);
    }

    public static function CatalogService(): CatalogService
    {
        // Bitrix автоматически создаёт класс и его зависимости без регистрации.
        return ServiceLocator::getInstance()->get(CatalogService::class);
    }

    public static function ValidationService(): ValidationService
    {
        return ServiceLocator::getInstance()->get('main.validation.service');
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
            ExceptionHandler::addToLog("Сервис $id не найден");
            return null;
        }

        try {
            return $locator->get($prefix . $id);
        } catch (ObjectNotFoundException|NotFoundExceptionInterface $e) {
            ExceptionHandler::handle($e);
            return null;
        }
    }

    public function has(string $id): bool
    {
        $locator = ServiceLocator::getInstance();
        return $locator->has(self::MODULE_PREFIX . $id);
    }
}
