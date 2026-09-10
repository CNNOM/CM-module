<?php
declare(strict_types=1);

namespace CloudMill\App\Favorites\Service;

use CloudMill\App\Catalog\Service\ProductCardService;
use CloudMill\App\Catalog\Service\Abstract\AbstractProductListService;

final class FavoritesService extends AbstractProductListService
{
    protected const LIMIT = 8;
    protected const COOKIE_NAME = 'favorites';
    protected const COOKIE_TTL = 60 * 60 * 24 * 7;
    protected const HL_SHARE = 'FavoritesShare';
    protected const INVALID_CODE = 'INVALID_PRODUCT';

    public static function remove(int $productId): array
    {
        $sectionId = ProductCardService::getSectionId($productId);

        return parent::remove($productId) + ['sectionId' => $sectionId];
    }

    public static function getProducts(): array
    {
        return array_values(ProductCardService::findByIds(self::getItems()));
    }

    protected static function getProductData(int $productId): array
    {
        return ProductCardService::findByIds([$productId])[$productId] ?? [];
    }

    protected static function errorResponse(string $code): array
    {
        $response = parent::errorResponse($code);
        if ($code !== 'LIMIT') {
            unset($response['count']);
        }

        return $response;
    }
}
