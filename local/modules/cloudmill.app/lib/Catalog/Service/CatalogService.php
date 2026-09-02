<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class CatalogService extends AbstractIblockService
{
    /** Код инфоблока каталога. */
    public const IBLOCK_CODE = 'catalog';

    /** Обязательные поля раздела каталога. */
    private const SECTION_SELECT = ['ID', 'NAME'];

    /** Обязательные поля товара каталога. */
    private const PRODUCT_SELECT = ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];

    /** Сервис получения торговых предложений товара. */
    public function __construct(private readonly OffersService $offersService)
    {
    }

    /** Получает название инфоблока каталога. */
    public function getName(): string
    {
        $iblockId = $this->getIblockId();
        if ($iblockId <= 0) {
            return '';
        }

        $iblockList = IblockManager::getIBlockList(
            filter: ['ID' => $iblockId],
            preferByID: true
        );

        return trim((string)($iblockList[$iblockId]['NAME'] ?? ''));
    }

    /** Получает товары каталога по ID. */
    public function findByIds(array $ids, array $select = []): array
    {
        $ids = $this->normalizeIds($ids);
        $iblockId = $this->getIblockId();

        if (!$ids || $iblockId <= 0) {
            return [];
        }

        $products = IblockManager::getList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: $this->withCatalogIblock([
                'ID' => $ids,
                'ACTIVE' => 'Y',
            ]),
            select: $this->mergeSelect(self::PRODUCT_SELECT, ['IBLOCK_SECTION_ID', ...$select]),
            preferByID: true
        );

        return $this->appendCatalogData($products);
    }

    /** Получает разделы каталога по фильтру. */
    public function getSections(array $filter = [], int $limit = 6, array $select = []): array
    {
        $filter = $this->withCatalogIblock($filter);

        return IblockManager::getSectionList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: $filter,
            select: $this->mergeSelect(self::SECTION_SELECT, $select),
            nav: ['nTopCount' => $limit]
        );
    }

    /** Получает товары каталога по фильтру. */
    public function getProducts(array $filter = [], array $select = [], bool $withOffers = false): array
    {
        $filter = $this->withCatalogIblock($filter);

        $products = IblockManager::getList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: $filter,
            select: $this->mergeSelect(self::PRODUCT_SELECT, $select)
        );

        return $this->appendCatalogData($products, $withOffers);
    }

    /** Получает торговые предложения конкретного товара. */
    public function getOffers(int $productId, array $select = []): array
    {
        return $this->offersService->getByProductId($productId, $select);
    }

    private function withCatalogIblock(array $filter): array
    {
        $filter['IBLOCK_ID'] = $this->getIblockId();

        return $filter;
    }

    /** Добавляет к товарам количество, цену, валюту и торговые предложения. */
    private function appendCatalogData(array $products, bool $withOffers = false): array
    {
        if (!$products || !Loader::includeModule('catalog')) {
            return $products;
        }

        foreach ($products as &$product) {
            $catalogProduct = \CCatalogProduct::GetByID((int)$product['ID']) ?: [];
            $basePrice = \CPrice::GetBasePrice((int)$product['ID']) ?: [];

            $product['QUANTITY'] = (float)($catalogProduct['QUANTITY'] ?? 0);
            $product['PRICE'] = isset($basePrice['PRICE']) ? (float)$basePrice['PRICE'] : null;
            $product['CURRENCY'] = (string)($basePrice['CURRENCY'] ?? '');

            if ($withOffers) {
                $product['OFFERS'] = $this->getOffers((int)$product['ID']);
            }
        }
        unset($product);

        return $products;
    }

}
