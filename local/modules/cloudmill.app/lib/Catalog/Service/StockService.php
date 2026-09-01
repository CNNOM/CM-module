<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use RuntimeException;

final class StockService
{
    public static function getAvailability(int $productId): array
    {
        self::loadCatalog();

        $hasWarehouse = false;
        $warehouseQuantity = 0.0;
        $storeProducts = \CCatalogStoreProduct::GetList(
            [],
            ['PRODUCT_ID' => $productId],
            false,
            false,
            ['ID', 'STORE_ID', 'AMOUNT']
        );

        while ($storeProduct = $storeProducts->Fetch()) {
            $amount = (float)$storeProduct['AMOUNT'];
            if ($amount > 0) {
                $hasWarehouse = true;
                $warehouseQuantity += $amount;
            }
        }

        $product = \CCatalogProduct::GetByID($productId) ?: [];

        return [
            'hasWarehouse' => $hasWarehouse,
            'warehouseQuantity' => $warehouseQuantity,
            'productQuantity' => (float)($product['QUANTITY'] ?? 0),
        ];
    }

    public static function limitQuantity(float $quantity, array $availability): float
    {
        $quantity = max(0, $quantity);

        return $availability['hasWarehouse']
            ? min($quantity, (float)$availability['warehouseQuantity'])
            : min($quantity, 100);
    }

    public static function decreaseForBasket(Basket $basket): array
    {
        self::loadCatalog();
        $changes = [];

        try {
            foreach ($basket as $basketItem) {
                $productId = (int)$basketItem->getProductId();
                $quantity = (float)$basketItem->getQuantity();
                $stores = self::getStores($productId);
                $availableQuantity = array_sum(array_column($stores, 'AMOUNT'));

                if ($availableQuantity <= 0) {
                    continue;
                }

                if ($availableQuantity < $quantity) {
                    throw new RuntimeException("Недостаточно товара на складах: {$productId}");
                }

                $changes[] = ['stores' => array_map(
                    static fn(array $store): array => [
                        'id' => (int)$store['ID'],
                        'amount' => (float)$store['AMOUNT'],
                    ],
                    $stores
                )];

                self::decreaseStores($stores, $quantity, $productId);
            }
        } catch (\Throwable $exception) {
            self::restore($changes);
            throw $exception;
        }

        return $changes;
    }

    public static function restore(array $changes): void
    {
        foreach ($changes as $change) {
            foreach ($change['stores'] as $store) {
                \CCatalogStoreProduct::Update($store['id'], [
                    'AMOUNT' => $store['amount'],
                ]);
            }
        }
    }

    private static function getStores(int $productId): array
    {
        $stores = [];
        $result = \CCatalogStoreProduct::GetList(
            ['ID' => 'ASC'],
            ['PRODUCT_ID' => $productId],
            false,
            false,
            ['ID', 'AMOUNT']
        );

        while ($store = $result->Fetch()) {
            $store['AMOUNT'] = (float)$store['AMOUNT'];
            $stores[] = $store;
        }

        return $stores;
    }

    private static function decreaseStores(array $stores, float $quantity, int $productId): void
    {
        $remaining = $quantity;

        foreach ($stores as $store) {
            if ($remaining <= 0) {
                break;
            }

            $decrease = min($remaining, (float)$store['AMOUNT']);
            if ($decrease > 0 && !\CCatalogStoreProduct::Update((int)$store['ID'], [
                'AMOUNT' => (float)$store['AMOUNT'] - $decrease,
            ])) {
                throw new RuntimeException("Не удалось обновить остаток на складе: {$productId}");
            }

            $remaining -= $decrease;
        }
    }

    private static function loadCatalog(): void
    {
        if (!Loader::includeModule('catalog')) {
            throw new RuntimeException('Не удалось подключить модуль catalog');
        }
    }
}
