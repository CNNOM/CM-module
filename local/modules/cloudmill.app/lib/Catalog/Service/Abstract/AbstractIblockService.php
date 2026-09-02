<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service\Abstract;

use CloudMill\App\Catalog\Service\Contract\IblockServiceInterface;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;

abstract class AbstractIblockService implements IblockServiceInterface
{
    /**
     * Получает код инфоблока конкретного сервиса.
     *
     * Код определяется константой IBLOCK_CODE дочернего класса.
     *
     * @return string Код инфоблока.
     */
    public function getIblockCode(): string
    {
        return static::IBLOCK_CODE;
    }

    /**
     * Получает ID инфоблока по его коду.
     *
     * @return int ID инфоблока или 0, если инфоблок не найден.
     */
    public function getIblockId(): int
    {
        return IblockManager::getID($this->getIblockCode());
    }

    /**
     * Оставляет только уникальные положительные ID.
     *
     * @param array<int> $ids Исходный список идентификаторов.
     * @return array<int> Нормализованный список ID.
     */
    protected function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));
    }

    /**
     * Объединяет обязательные поля с дополнительными полями выборки.
     *
     * @param array<string> $baseSelect Обязательные поля.
     * @param array<string> $select Дополнительные поля.
     * @return array<string> Уникальный список полей.
     */
    protected function mergeSelect(array $baseSelect, array $select): array
    {
        return array_values(array_unique(array_merge($baseSelect, $select)));
    }
}
