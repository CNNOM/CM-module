<?php
declare(strict_types=1);

namespace CloudMill\App\Basket\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use CloudMill\App\Basket\Service\BasketService;
use CloudMill\App\Basket\Service\ServiceProvider;
use Throwable;

final class BasketController extends Controller
{
    public function configureActions(): array
    {
        $post = [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                new ActionFilter\Csrf(),
            ],
            '-prefilters' => [ActionFilter\Authentication::class],
        ];

        return [
            'get' => [
                'prefilters' => [new ActionFilter\HttpMethod([
                    ActionFilter\HttpMethod::METHOD_GET,
                    ActionFilter\HttpMethod::METHOD_POST,
                ])],
                '-prefilters' => [ActionFilter\Authentication::class],
            ],
            'refresh' => [
                'prefilters' => [new ActionFilter\HttpMethod([
                    ActionFilter\HttpMethod::METHOD_GET,
                    ActionFilter\HttpMethod::METHOD_POST,
                ])],
                '-prefilters' => [ActionFilter\Authentication::class],
            ],
            'add' => $post,
            'remove' => $post,
            'update' => $post,
        ];
    }

    public function getAction(): array
    {
        return $this->getBasketService()->getItems();
    }

    public function refreshAction(): array
    {
        return $this->getBasketService()->refresh();
    }

    public function addAction(int $productId, float $quantity = 1): array
    {
        return $this->executeAction(fn() => $this->getBasketService()->add($productId, $quantity));
    }

    public function removeAction(int $productId): array
    {
        return $this->executeAction(fn() => $this->getBasketService()->remove($productId));
    }

    public function updateAction(int $productId, float $quantity): array
    {
        return $this->executeAction(fn() => $this->getBasketService()->update($productId, $quantity));
    }

    private function getBasketService(): BasketService
    {
        return ServiceProvider::BasketService()
            ?? throw new \RuntimeException('Сервис корзины не найден');
    }

    private function executeAction(callable $action): array
    {
        try {
            return $action();
        } catch (Throwable $exception) {
            $this->addError(new Error($exception->getMessage()));
            return [];
        }
    }
}
