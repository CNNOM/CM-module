<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;
use RuntimeException;

final class PriceService
{
    /**
     * Получает базовую цену и валюту товара.
     *
     * @param int $productId ID товара или торгового предложения.
     * @return array{price: float|null, currency: string} Данные цены.
     */
    public function getBasePrice(int $productId): array
    {
        if ($productId <= 0) {
            return ['price' => null, 'currency' => ''];
        }

        if (!Loader::includeModule('catalog')) {
            throw new RuntimeException('Не удалось подключить модуль catalog');
        }

        $price = \CPrice::GetBasePrice($productId) ?: [];

        return [
            'price' => isset($price['PRICE']) ? (float)$price['PRICE'] : null,
            'currency' => (string)($price['CURRENCY'] ?? ''),
        ];
    }
}
