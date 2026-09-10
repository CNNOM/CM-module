<?php
declare(strict_types=1);

namespace CloudMill\App\Order\Service;

use Bitrix\Sale\Order;
use CloudMill\App\Order\Dto\OrderDataDto;
use RuntimeException;

final class OrderDeliveryService
{
    public static function apply(Order $order, OrderDataDto $data): void
    {
        $deliveryId = $data->deliveryId;
        $availableDeliveries = DeliveryService::getAvailableServices();

        if (!$deliveryId && $availableDeliveries) {
            $deliveryId = (int)$availableDeliveries[0]['ID'];
        }

        if (!$deliveryId) {
            return;
        }

        $allowedIds = array_map(
            static fn(array $delivery): int => (int)$delivery['ID'],
            $availableDeliveries
        );
        if (!in_array($deliveryId, $allowedIds, true)) {
            throw new RuntimeException('Выбранный способ доставки недоступен');
        }

        $shipment = $order->getShipmentCollection()->createItem();
        $shipment->setFields([
            'DELIVERY_ID' => $deliveryId,
            'CURRENCY' => $order->getCurrency(),
        ]);

        foreach ($order->getBasket() as $basketItem) {
            $shipmentItem = $shipment->getShipmentItemCollection()->createItem($basketItem);
            $shipmentItem->setQuantity($basketItem->getQuantity());
        }
    }
}
