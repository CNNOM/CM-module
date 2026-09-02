<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

interface IblockServiceInterface
{
    /** Получает код инфоблока сервиса. */
    public function getIblockCode(): string;

    /** Получает ID инфоблока сервиса. */
    public function getIblockId(): int;
}
