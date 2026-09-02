<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

abstract class AbstractIblockService implements IblockServiceInterface
{
    /** Получает код инфоблока конкретного сервиса. */
    public function getIblockCode(): string
    {
        return static::IBLOCK_CODE;
    }

    /** Получает ID инфоблока по его коду. */
    public function getIblockId(): int
    {
        return IblockManager::getID($this->getIblockCode());
    }

    /** Оставляет только уникальные положительные ID. */
    protected function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));
    }

    /** Объединяет обязательные поля с дополнительными полями выборки. */
    protected function mergeSelect(array $baseSelect, array $select): array
    {
        return array_values(array_unique(array_merge($baseSelect, $select)));
    }
}
