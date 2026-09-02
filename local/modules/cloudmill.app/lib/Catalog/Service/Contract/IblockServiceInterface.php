<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service\Contract;

interface IblockServiceInterface
{
    /**
     * Получает код инфоблока сервиса.
     *
     * @return string Код инфоблока.
     */
    public function getIblockCode(): string;

    /**
     * Получает ID инфоблока сервиса.
     *
     * @return int ID инфоблока или 0, если инфоблок не найден.
     */
    public function getIblockId(): int;
}
