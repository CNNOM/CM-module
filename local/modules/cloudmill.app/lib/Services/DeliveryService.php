<?php
declare(strict_types=1);

namespace Cloudmill\App\Services;

use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\Services\Manager;

final class DeliveryService
{
    public static function getAvailableServices(): array
    {
        if (!Loader::includeModule('sale')) {
            return [];
        }

        $services = array_values(array_filter(
            Manager::getActiveList(),
            static function (array $service): bool {
                return (int)($service['ID'] ?? 0) > 0
                    && mb_strtolower(trim((string)($service['NAME'] ?? ''))) !== 'без доставки';
            }
        ));

        usort(
            $services,
            static fn(array $first, array $second): int => [
                (int)($first['SORT'] ?? 500),
                (int)($first['ID'] ?? 0),
            ] <=> [
                (int)($second['SORT'] ?? 500),
                (int)($second['ID'] ?? 0),
            ]
        );

        return $services;
    }
}
