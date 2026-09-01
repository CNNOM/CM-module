<?php
declare(strict_types=1);

namespace Cloudmill\App\Services;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use RuntimeException;

final class BasketService
{
    public function __construct(private Basket $basket)
    {
    }

    public function getProductIds(): array
    {
        $productIds = [];

        foreach ($this->basket as $item) {
            $productIds[] = (int)$item->getProductId();
        }

        return $productIds;
    }

    public function refresh(): array
    {
        Loader::includeModule('sale');

        $this->basket = Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);

        return $this->getItems();
    }

    public function getItems(): array
    {
        $items = [];

        foreach ($this->basket as $item) {
            $productId = (int)$item->getProductId();
            $product = \CIBlockElement::GetList([], ['ID' => $productId], false, false, ['ID', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch() ?: [];
            $pictureId = $product['PREVIEW_PICTURE'] ?: ($product['DETAIL_PICTURE'] ?? null);
            $sectionId = (int)($product['IBLOCK_SECTION_ID'] ?? 0);
            $section = $sectionId
                ? \CIBlockSection::GetList([], ['ID' => $sectionId], false, ['ID', 'NAME'])->Fetch()
                : null;
            $availability = StockService::getAvailability($productId);

            $items[] = [
                'id' => (int)$item->getId(),
                'productId' => $productId,
                'name' => (string)$item->getField('NAME'),
                'image' => (int)$pictureId,
                'imageUrl' => $pictureId ? (string)\CFile::GetPath($pictureId) : '',
                'section' => [ 
                    'id' => $sectionId,
                    'name' => (string)($section['NAME'] ?? ''),
                ],
                'quantity' => (float)$item->getQuantity(),
                'price' => (float)$item->getPrice(),
                'currency' => $item->getCurrency(),
                'hasWarehouse' => $availability['hasWarehouse'],
                'warehouseQuantity' => $availability['warehouseQuantity'],
                'productQuantity' => $availability['productQuantity'],
            ];
        }

        return $items;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->basket as $item) {
            $count++;
        }

        return $count;
    }

    public function add(int $productId, float $quantity = 1): array
    {
        $basket = $this->basket;
        $item = $basket->getExistsItem('catalog', $productId);
        $availability = StockService::getAvailability($productId);

        if ($item) {
            $limitedQuantity = StockService::limitQuantity((float)$item->getQuantity(), $availability);
            if ($limitedQuantity !== (float)$item->getQuantity()) {
                $limitedQuantity > 0
                    ? $item->setField('QUANTITY', $limitedQuantity)
                    : $item->delete();
                $this->save();
            }

            return $this->refresh();
        }

        $quantity = StockService::limitQuantity($quantity, $availability);
        if ($quantity <= 0) {
            return $this->refresh();
        }

        $item = $basket->createItem('catalog', $productId);
        $item->setFields([
            'QUANTITY' => $quantity,
            'CURRENCY' => CurrencyManager::getBaseCurrency(),
            'LID' => SITE_ID,
            'PRODUCT_PROVIDER_CLASS' => \CCatalogProductProvider::class,
        ]);
        $this->save();

        return $this->refresh();
    }

    public function remove(int $productId): array
    {
        $basket = $this->basket;
        foreach ($basket as $item) {
            if ((int)$item->getProductId() === $productId) {
                $item->delete();
                break;
            }
        }
        $this->save();

        return $this->refresh();
    }

    public function update(int $productId, float $quantity): array
    {
        $basket = $this->basket;
        $item = null;

        foreach ($basket as $basketItem) {
            if ((int)$basketItem->getProductId() === $productId) {
                $item = $basketItem;
                break;
            }
        }

        if ($item) {
            $quantity = StockService::limitQuantity($quantity, StockService::getAvailability((int)$item->getProductId()));
            $quantity > 0 ? $item->setField('QUANTITY', $quantity) : $item->delete();
            $this->save();
        }

        return $this->refresh();
    }

    private function save(): void
    {
        $result = $this->basket->save();

        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

}
