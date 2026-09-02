<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use CloudMill\App\Catalog\Service\Abstract\AbstractIblockService;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class OffersService extends AbstractIblockService
{
    /** Код инфоблока торговых предложений. */
    public const IBLOCK_CODE = 'offers';

    /** Обязательные поля торгового предложения. */
    private const SELECT = ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];

    /** Сервис коммерческих данных торговых предложений. */
    public function __construct(private readonly ProductDataService $productDataService)
    {
    }

    /**
     * Получает активные торговые предложения конкретного товара.
     *
     * К стандартным полям добавляются количество, базовая цена и валюта.
     * Дополнительные поля передаются через параметр $select.
     *
     * @param int $productId ID товара каталога из свойства CML2_LINK.
     * @param array<string> $select Дополнительные поля и свойства ТП.
     * @return array<int, array<string, mixed>> Список торговых предложений.
     */
    public function getByProductId(int $productId, array $select = []): array
    {
        if ($productId <= 0) {
            return [];
        }

        $iblockId = $this->getIblockId();
        if ($iblockId <= 0) {
            return [];
        }

        $offers = IblockManager::getList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: [
                'IBLOCK_ID' => $iblockId,
                'PROPERTY_CML2_LINK' => $productId,
                'ACTIVE' => 'Y',
                'PROPERTY_CML2_LINK.ACTIVE' => 'Y',
            ],
            select: $this->mergeSelect([...self::SELECT, 'PROPERTY_CML2_LINK'], $select),
            preferByID: true
        );

        return $this->productDataService->append($offers);
    }

    /**
     * Получает активные торговые предложения по их ID.
     *
     * @param array<int> $ids ID торговых предложений.
     * @param array<string> $select Дополнительные поля и свойства ТП.
     * @return array<int, array<string, mixed>> Торговые предложения, индексированные по ID.
     */
    public function findByIds(array $ids, array $select = []): array
    {
        $ids = $this->normalizeIds($ids);
        $iblockId = $this->getIblockId();
        if (!$ids || $iblockId <= 0) {
            return [];
        }

        $offers = IblockManager::getList(
            order: ['SORT' => 'ASC', 'NAME' => 'ASC'],
            filter: [
                'IBLOCK_ID' => $iblockId,
                'ID' => $ids,
                'ACTIVE' => 'Y',
            ],
            select: $this->mergeSelect(self::SELECT, $select),
            preferByID: true
        );

        return $this->productDataService->append($offers);
    }

}
