<?php

declare(strict_types=1);

namespace CloudMill\App\Order\Service;

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use CloudMill\App\Order\Dto\OrderDataDto;
use CloudMill\App\Infrastructure\Bitrix\DI\ServiceProvider;
use CloudMill\App\Catalog\Service\WarehouseStockService;
use CloudMill\App\Order\Validator\OrderValidator;
use RuntimeException;

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
        OrderDeliveryService::apply($order, $data);
        OrderPaymentService::apply($order);

        $stockChanges = WarehouseStockService::decreaseForBasket($basket);

        try {
            $order->doFinalAction(true);
            $result = $order->save();
        } catch (\Throwable $exception) {
            WarehouseStockService::restore($stockChanges);
            throw $exception;
        }

        if (!$result->isSuccess()) {
            WarehouseStockService::restore($stockChanges);
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        $orderId = (int)$order->getId();
        $_SESSION[self::LAST_GUEST_ORDER_SESSION_KEY] = $orderId;

        // Уведомление не должно отменять успешное оформление заказа.
        try {
            OrderNotificationService::send($order, $data);
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

}
