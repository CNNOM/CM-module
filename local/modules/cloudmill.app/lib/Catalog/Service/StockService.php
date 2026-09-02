<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;

final class StockService
{
    /** Получает количество товара из Bitrix Catalog. */
    public static function getQuantity(int $productId): float
    {
        self::loadCatalog();

        if ($productId <= 0) {
            return 0.0;
        }

        $product = \CCatalogProduct::GetByID($productId) ?: [];

        return (float)($product['QUANTITY'] ?? 0);
    }

    /** Получает количество товара и остатки по складам. */
    public static function getAvailability(int $productId): array
    {
        return array_merge(
            WarehouseStockService::getAvailability($productId),
            ['productQuantity' => self::getQuantity($productId)]
        );
    }

    /** Ограничивает количество товара доступным остатком. */
    public static function limitQuantity(float $quantity, array $availability): float
    {
        $quantity = max(0, $quantity);

        return $availability['hasWarehouse']
            ? min($quantity, (float)$availability['warehouseQuantity'])
            : min($quantity, 100);
    }

    private static function loadCatalog(): void
    {
        if (!Loader::includeModule('catalog')) {
            throw new \RuntimeException('Не удалось подключить модуль catalog');
        }
    }
}
