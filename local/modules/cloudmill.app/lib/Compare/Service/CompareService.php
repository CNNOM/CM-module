<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Service;

use Bitrix\Main\DI\ServiceLocator;
use CFile;
use CloudMill\App\Catalog\Service\CatalogService;
use CloudMill\App\Catalog\Service\Abstract\AbstractProductListService;

final class CompareService extends AbstractProductListService
{
    public const LIMIT = 8;
    protected const COOKIE_NAME = 'compare';
    protected const COOKIE_TTL = 60 * 60 * 24 * 7;
    protected const HL_SHARE = 'CompareShare';

    protected static function getProductData(int $productId): array
    {
        $catalogService = ServiceLocator::getInstance()->get(CatalogService::class);
        $item = $catalogService->findByIds([$productId])[$productId] ?? null;
        if (!$item) {
            return [];
        }

        $imageId = (int)($item['PREVIEW_PICTURE'] ?: $item['DETAIL_PICTURE']);

        return [
            'id' => (int)$item['ID'],
            'name' => (string)$item['NAME'],
            'url' => (string)$item['DETAIL_PAGE_URL'],
            'image' => $imageId > 0 ? (string)CFile::GetPath($imageId) : '',
        ];
    }
}
