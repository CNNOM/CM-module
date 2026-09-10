<?php
declare(strict_types=1);

namespace CloudMill\App\Order\Service;

use Bitrix\Sale\Order;
use CEvent;
use CloudMill\App\Order\Dto\OrderDataDto;

final class OrderNotificationService
{
    public static function send(Order $order, OrderDataDto $data): void
    {
        $email = $data->email();
        if ($email === '') {
            return;
        }

        $basketItems = [];
        foreach ($order->getBasket() as $basketItem) {
            $basketItems[] = sprintf(
                '%s — %s шт. × %s %s',
                (string)$basketItem->getField('NAME'),
                (float)$basketItem->getQuantity(),
                (float)$basketItem->getPrice(),
                (string)$basketItem->getCurrency()
            );
        }

        $fields = [
            'EMAIL' => $email,
            'ORDER_ID' => (int)$order->getId(),
            'CUSTOMER_NAME' => $data->customerName(),
            'PHONE' => $data->phone(),
            'CUSTOMER_TYPE' => $data->isLegal() ? 'Юридическое лицо' : 'Физическое лицо',
            'COMPANY_NAME' => $data->companyName,
            'ORDER_PRICE' => (float)$order->getPrice(),
            'CURRENCY' => (string)$order->getCurrency(),
            'DATE_SHIPMENT' => $data->shippingDate,
            'COMMENT' => $data->comment,
            'ORDER_ITEMS' => implode('<br>', $basketItems),
        ];

        CEvent::Send('CREATE_ORDER', SITE_ID, ['EMAIL_TO' => $email] + $fields, 'Y', '', ['CONTENT_TYPE' => 'text/html']);
        CEvent::Send('NEW_ORDER_MANAGER', SITE_ID, $fields, 'Y', '', []);
    }
}
