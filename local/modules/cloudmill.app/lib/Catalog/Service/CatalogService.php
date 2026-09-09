<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use CloudMill\App\Catalog\Service\Abstract\AbstractIblockService;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class CatalogService extends AbstractIblockService
{
    /** Код инфоблока каталога. */
    public const IBLOCK_CODE = 'catalog';

    /** Обязательные поля раздела каталога. */
    private const SECTION_SELECT = ['ID', 'NAME'];

    /** Обязательные поля товара каталога. */
    private const PRODUCT_SELECT = ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];

    /**
     * Сервисы получения ТП и коммерческих данных товаров.
     *
     * @param OffersService $offersService Сервис торговых предложений.
     * @param ProductDataService $productDataService Сервис цены и количества.
     */
    public function __construct(
        private readonly OffersService $offersService,
        private readonly ProductDataService $productDataService,
    )
    {
    }

    /**
     * Получает название инфоблока каталога.
     *
     * @return string Название каталога или пустая строка, если инфоблок не найден.
     */
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

    /**
     * Получает активные товары каталога по их ID.
     *
     * К стандартным полям добавляются количество, базовая цена и валюта.
     * Дополнительные поля передаются через параметр $select.
     *
     * @param array<int> $ids ID товаров каталога.
     * @param array<string> $select Дополнительные поля и свойства товара.
     * @return array<int, array<string, mixed>> Товары, индексированные по ID.
     */
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

        return $this->productDataService->append($products);
    }

    /**
     * Получает разделы каталога.
     *
     * К переданному фильтру автоматически добавляются ID каталожного инфоблока.
     *
     * @param array<string|int, mixed> $filter Дополнительные условия выборки.
     * @param int $limit Максимальное количество разделов.
     * @param array<string> $select Дополнительные поля раздела.
     * @return array<int, array<string, mixed>> Список разделов каталога.
     */
    public function getSections(
        array $filter = [],
        int $limit = 6,
        array $select = [],
        bool $withCount = false
    ): array
    {
        $filter = $this->withCatalogIblock([
            'GLOBAL_ACTIVE' => 'Y',
            ...$filter,
        ]);

        $sections = IblockManager::getSectionList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: $filter,
            bIncCnt: $withCount,
            select: $this->mergeSelect(self::SECTION_SELECT, $select),
            nav: $limit > 0 ? ['nTopCount' => $limit] : false
        );

        error_log('[CatalogService::getSections] ' . json_encode([
            'filter' => $filter,
            'limit' => $limit,
            'withCount' => $withCount,
            'count' => count($sections),
            'sections' => array_map(static fn(array $section): array => [
                'ID' => $section['ID'] ?? null,
                'CODE' => $section['CODE'] ?? null,
                'NAME' => $section['NAME'] ?? null,
                'DEPTH_LEVEL' => $section['DEPTH_LEVEL'] ?? null,
                'ELEMENT_CNT' => $section['ELEMENT_CNT'] ?? null,
            ], $sections),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $sections;
    }

    /** Получает один раздел каталога по символьному коду. */
    public function getSectionByCode(string $code, array $select = []): array
    {
        return $this->getSections(
            filter: ['CODE' => $code],
            limit: 1,
            select: $select
        )[0] ?? [];
    }

    /**
     * Получает товары каталога по фильтру.
     *
     * К стандартным полям автоматически добавляются количество, базовая цена
     * и валюта. При $withOffers товары также получают массив торговых предложений
     * в поле OFFERS.
     *
     * @param array<string|int, mixed> $filter Дополнительные условия выборки.
     * @param array<string> $select Дополнительные поля и свойства товара.
     * @param bool $withOffers Добавлять ли торговые предложения товаров.
     * @return array<int, array<string, mixed>> Список товаров каталога.
     */
    public function getProducts(array $filter = [], array $select = [], bool $withOffers = false): array
    {
        $filter = $this->withCatalogIblock($filter);

        $products = IblockManager::getList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: $filter,
            select: $this->mergeSelect(self::PRODUCT_SELECT, $select)
        );

        $products = $this->productDataService->append($products);

        if ($withOffers) {
            foreach ($products as &$product) {
                $product['OFFERS'] = $this->getOffers((int)$product['ID']);
            }
            unset($product);
        }

        return $products;
    }

    /**
     * Получает торговые предложения конкретного товара.
     *
     * @param int $productId ID товара каталога.
     * @param array<string> $select Дополнительные поля и свойства торгового предложения.
     * @return array<int, array<string, mixed>> Список торговых предложений.
     */
    public function getOffers(int $productId, array $select = []): array
    {
        return $this->offersService->getByProductId($productId, $select);
    }

    /**
     * Добавляет ID каталожного инфоблока в фильтр.
     *
     * @param array<string|int, mixed> $filter Исходный фильтр.
     * @return array<string|int, mixed> Фильтр с ограничением по каталогу.
     */
    private function withCatalogIblock(array $filter): array
    {
        $filter['IBLOCK_ID'] = $this->getIblockId();

        return $filter;
    }

}
