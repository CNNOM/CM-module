<?php
declare(strict_types=1);

namespace CloudMill\App\Order\Service;

use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;

final class OrderPaymentService
{
    public static function apply(Order $order): void
    {
        $paySystem = PaySystem\Manager::getList([
            'filter' => ['ACTIVE' => 'Y'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
            'limit' => 1,
        ])->fetch();

        if (!$paySystem) {
            return;
        }

        $service = PaySystem\Manager::getObjectById((int)$paySystem['ID']);
        if (!$service) {
            return;
        }

        $payment = $order->getPaymentCollection()->createItem($service);
        $payment->setField('SUM', $order->getPrice());
        $payment->setField('CURRENCY', $order->getCurrency());
    }
}
