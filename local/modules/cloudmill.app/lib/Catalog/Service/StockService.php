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
        return self::getAvailabilityByIds([$productId])[$productId] ?? [
            'hasWarehouse' => false,
            'warehouseQuantity' => 0.0,
            'productQuantity' => 0.0,
        ];
    }

    /** Получает наличие всех переданных товаров пакетно. */
    public static function getAvailabilityByIds(array $productIds): array
    {
        self::loadCatalog();
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $id): bool => $id > 0)));
        $availability = WarehouseStockService::getAvailabilityByIds($productIds);

        if (!$productIds) {
            return $availability;
        }

        $products = \CCatalogProduct::GetList([], ['ID' => $productIds], false, false, ['ID', 'QUANTITY']);
        while ($product = $products->Fetch()) {
            $productId = (int)$product['ID'];
            $availability[$productId]['productQuantity'] = (float)$product['QUANTITY'];
        }

        foreach ($availability as $productId => &$item) {
            $item['productQuantity'] ??= 0.0;
        }
        unset($item);

        return $availability;
    }

    /** Ограничивает количество товара доступным остатком. */
    public static function limitQuantity(float $quantity, array $availability): float
    {
        $quantity = max(0, $quantity);

        return $availability['hasWarehouse']
            ? min($quantity, max(0, (float)$availability['warehouseQuantity']))
            : min($quantity, max(0, (float)$availability['productQuantity']), 100);
    }

    private static function loadCatalog(): void
    {
        if (!Loader::includeModule('catalog')) {
            throw new \RuntimeException('Не удалось подключить модуль catalog');
        }
    }
}
