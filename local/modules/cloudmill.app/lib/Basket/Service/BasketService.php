<?php
declare(strict_types=1);

namespace CloudMill\App\Basket\Service;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use CloudMill\App\Catalog\Service\StockService;
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
        if (!Loader::includeModule('sale')) {
            throw new RuntimeException('Не удалось подключить модуль sale');
        }

        $this->basket = Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);

        return $this->getItems();
    }

    public function getItems(): array
    {
        $ids = array_values(array_unique($this->getProductIds()));
        if (!$ids) {
            return [];
        }
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock');
        }

        $products = [];
        $result = \CIBlockElement::GetList([], ['ID' => $ids], false, false,
            ['ID', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']);
        while ($product = $result->Fetch()) {
            $products[(int)$product['ID']] = $product;
        }

        $sections = [];
        $sectionIds = array_values(array_unique(array_filter(array_column($products, 'IBLOCK_SECTION_ID'))));
        if ($sectionIds) {
            $result = \CIBlockSection::GetList([], ['ID' => $sectionIds], false, ['ID', 'NAME']);
            while ($section = $result->Fetch()) {
                $sections[(int)$section['ID']] = $section;
            }
        }

        $items = [];
        foreach ($this->basket as $item) {
            $productId = (int)$item->getProductId();
            $product = $products[$productId] ?? [];
            $pictureId = (int)(($product['PREVIEW_PICTURE'] ?? 0) ?: ($product['DETAIL_PICTURE'] ?? 0));
            $sectionId = (int)($product['IBLOCK_SECTION_ID'] ?? 0);
            $section = $sections[$sectionId] ?? [];
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
        $this->validateInput($productId, $quantity);
        if ($quantity <= 0) {
            throw new RuntimeException('Количество для добавления должно быть больше нуля');
        }

        $basket = $this->basket;
        $item = $basket->getExistsItem('catalog', $productId);
        $availability = StockService::getAvailability($productId);
        $quantity = StockService::limitQuantity($quantity + ($item ? (float)$item->getQuantity() : 0), $availability);

        if ($quantity <= 0) {
            throw new RuntimeException('Товар отсутствует в наличии');
        }

        if ($item) {
            $this->checkResult($item->setField('QUANTITY', $quantity));
            $this->save();
            return $this->refresh();
        }

        $item = $basket->createItem('catalog', $productId);
        $this->checkResult($item->setFields([
            'QUANTITY' => $quantity,
            'CURRENCY' => CurrencyManager::getBaseCurrency(),
            'LID' => SITE_ID,
            'PRODUCT_PROVIDER_CLASS' => \CCatalogProductProvider::class,
        ]));
        $this->save();

        return $this->refresh();
    }

    public function remove(int $productId): array
    {
        $this->validateInput($productId);
        $item = $this->basket->getExistsItem('catalog', $productId);
        if ($item) {
            $this->checkResult($item->delete());
            $this->save();
        }

        return $this->refresh();
    }

    public function update(int $productId, float $quantity): array
    {
        $this->validateInput($productId, $quantity);
        $item = $this->basket->getExistsItem('catalog', $productId);

        if ($item) {
            $quantity = StockService::limitQuantity($quantity, StockService::getAvailability((int)$item->getProductId()));
            $this->checkResult($quantity > 0 ? $item->setField('QUANTITY', $quantity) : $item->delete());
            $this->save();
        }

        return $this->refresh();
    }

    private function save(): void
    {
        $this->checkResult($this->basket->save());
    }

    private function validateInput(int $productId, float $quantity = 0): void
    {
        if ($productId <= 0 || !is_finite($quantity) || $quantity < 0) {
            throw new RuntimeException('Некорректный товар или количество');
        }
    }

    private function checkResult(Result $result): void
    {
        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

}
