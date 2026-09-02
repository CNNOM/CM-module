<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

final class ProductDataService
{
    /** Сервис получения цены товара. */
    public function __construct(private readonly PriceService $priceService)
    {
    }

    /**
     * Добавляет к товарам количество, цену и валюту.
     *
     * @param array<int, array<string, mixed>> $products Товары или ТП.
     * @return array<int, array<string, mixed>> Обогащённые товары.
     */
    public function append(array $products): array
    {
        foreach ($products as &$product) {
            $productId = (int)($product['ID'] ?? 0);
            $price = $this->priceService->getBasePrice($productId);

            $product['QUANTITY'] = StockService::getQuantity($productId);
            $product['PRICE'] = $price['price'];
            $product['CURRENCY'] = $price['currency'];
        }
        unset($product);

        return $products;
    }
}
