<?php

declare(strict_types=1);

namespace CloudMill\App\Order\Service;

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;
use CloudMill\App\Order\Dto\OrderDataDto;
use CloudMill\App\Basket\Service\ServiceProvider;
use CloudMill\App\Order\Validator\OrderValidator;
use RuntimeException;
use CEvent;

final class OrderService
{
    private const LAST_GUEST_ORDER_SESSION_KEY = 'CLOUDMILL_LAST_ORDER_ID';

    public static function create(array $inputs): int
    {
        self::loadModules();

        $data = (new OrderValidator())->validate($inputs);
        $customerType = $data->customerType;
        $personTypeId = $data->isLegal() ? 2 : 1;
        $basket = ServiceProvider::Basket()
            ?? throw new RuntimeException('Сервис корзины не найден');

        if ($basket->isEmpty()) {
            throw new RuntimeException('Корзина пуста');
        }

        global $USER;
        $userId = $USER->IsAuthorized()
            ? (int)$USER->GetID()
            : (int)\CSaleUser::GetAnonymousUserID();

        if ($userId <= 0) {
            throw new RuntimeException('Не удалось определить пользователя для заказа');
        }

        $order = Order::create(SITE_ID, $userId);
        $order->setPersonTypeId($personTypeId);
        $order->setBasket($basket);

        self::setProperties($order, $data);
        self::setComment($order, $data);
        self::setDelivery($order, $data);
        self::setPayment($order);

        $stockChanges = StockService::decreaseForBasket($basket);

        try {
            $order->doFinalAction(true);
            $result = $order->save();
        } catch (\Throwable $exception) {
            StockService::restore($stockChanges);
            throw $exception;
        }

        if (!$result->isSuccess()) {
            StockService::restore($stockChanges);
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        $orderId = (int)$order->getId();
        $_SESSION[self::LAST_GUEST_ORDER_SESSION_KEY] = $orderId;

        // Уведомление не должно отменять успешное оформление заказа.
        try {
            self::sendOrderEmail($order, $data);
        } catch (\Throwable $exception) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ORDER_EMAIL_ERROR',
                'MODULE_ID' => 'cloudmill.app',
                'ITEM_ID' => (string)$orderId,
                'DESCRIPTION' => $exception->getMessage(),
            ]);
        }

        return $orderId;
    }

    public static function canAccess(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        if (!Loader::includeModule('sale')) {
            return false;
        }

        $order = Order::load($orderId);
        if (!$order) {
            return false;
        }

        global $USER;
        if ($USER->IsAuthorized()) {
            return (int)$order->getUserId() === (int)$USER->GetID();
        }

        return (int)($_SESSION[self::LAST_GUEST_ORDER_SESSION_KEY] ?? 0) === $orderId;
    }

    private static function loadModules(): void
    {
        foreach (['sale', 'catalog', 'iblock'] as $module) {
            if (!Loader::includeModule($module)) {
                throw new RuntimeException("Не удалось подключить модуль {$module}");
            }
        }
    }

    private static function setProperties(Order $order, OrderDataDto $data): void
    {
        $propertyCollection = $order->getPropertyCollection();
        $properties = $data->isLegal()
            ? [
                'CONTACT_PERSON' => $data->companyContactName, 'PHONE' => $data->companyPhone,
                'EMAIL' => $data->companyEmail, 'INN' => $data->companyInn,
                'COMPANY_NAME' => $data->companyName, 'DATE_SHIPMENT' => $data->shippingDate,
            ]
            : [
                'FIO' => $data->personName, 'PHONE' => $data->personPhone,
                'EMAIL' => $data->personEmail, 'ADDRESS' => $data->address,
                'DATE_SHIPMENT' => $data->shippingDate,
            ];

        foreach ($properties as $code => $value) {
            $property = $propertyCollection->getItemByOrderPropertyCode($code);
            if ($property && trim((string)$value) !== '') {
                $property->setValue(trim((string)$value));
            }
        }
    }

    private static function setComment(Order $order, OrderDataDto $data): void
    {
        $comment = $data->comment;
        if ($comment !== '') {
            $order->setField('USER_DESCRIPTION', $comment);
        }
    }

    private static function setDelivery(Order $order, OrderDataDto $data): void
    {
        $deliveryId = $data->deliveryId;
        $availableDeliveries = DeliveryService::getAvailableServices();

        if (!$deliveryId && $availableDeliveries) {
            $deliveryId = (int)$availableDeliveries[0]['ID'];
        }

        if (!$deliveryId) {
            return;
        }

        $allowedIds = array_map(static fn(array $delivery): int => (int)$delivery['ID'], $availableDeliveries);
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

    private static function setPayment(Order $order): void
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

    private static function sendOrderEmail(Order $order, OrderDataDto $data): void
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

        CEvent::Send(
            'CREATE_ORDER',
            SITE_ID,
            [
                'EMAIL_TO' => $email,
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
            ],
            'Y',
            '',
            ['CONTENT_TYPE' => 'text/html']
        );

        CEvent::Send(
            'NEW_ORDER_MANAGER',
            SITE_ID,
            [
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
            ],
            'Y',
            '',
            []
        );
    }
}
