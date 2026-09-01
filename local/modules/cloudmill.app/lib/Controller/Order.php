<?php
declare(strict_types=1);

namespace Cloudmill\App\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Cloudmill\App\Services\OrderService;
use Throwable;

final class Order extends Controller
{
    public function configureActions(): array
    {
        return [
            'createOrder' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
                '-prefilters' => [ActionFilter\Authentication::class],
            ],
        ];
    }

    public function createOrderAction(array $inputs = []): array
    {
        try {
            return ['orderId' => OrderService::create($inputs)];
        } catch (Throwable $exception) {
            $this->addError(new Error($exception->getMessage()));
            return [];
        }
    }
}
