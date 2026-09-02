<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use Bitrix\Main\Loader;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

final class OffersService extends AbstractIblockService
{
    /** Код инфоблока торговых предложений. */
    public const IBLOCK_CODE = 'offers';

    /** Обязательные поля торгового предложения. */
    private const SELECT = ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];

    /** Получает торговые предложения конкретного товара. */
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

        return $this->appendCatalogData($offers);
    }

    /** Получает торговые предложения по их ID. */
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

        return $this->appendCatalogData($offers);
    }

    /** Добавляет к торговым предложениям количество, цену и валюту. */
    private function appendCatalogData(array $offers): array
    {
        if (!$offers || !Loader::includeModule('catalog')) {
            return $offers;
        }

        foreach ($offers as &$offer) {
            $catalogProduct = \CCatalogProduct::GetByID((int)$offer['ID']) ?: [];
            $basePrice = \CPrice::GetBasePrice((int)$offer['ID']) ?: [];

            $offer['QUANTITY'] = (float)($catalogProduct['QUANTITY'] ?? 0);
            $offer['PRICE'] = isset($basePrice['PRICE']) ? (float)$basePrice['PRICE'] : null;
            $offer['CURRENCY'] = (string)($basePrice['CURRENCY'] ?? '');
        }
        unset($offer);

        return $offers;
    }
}
