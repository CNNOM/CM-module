<?php

namespace Cloudmill\App\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Cloudmill\App\Services\FavoriteService;

final class Favorite extends Controller
{
    protected array $response = [];
    public function configureActions(): array
    {
        $commonFilters = [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                new ActionFilter\Csrf(),
            ],
            '-prefilters' => [
                ActionFilter\Authentication::class,
            ],
        ];

        return [
            'add'    => $commonFilters,
            'remove' => $commonFilters,
            'clear'  => $commonFilters,
        ];
    }

    public function addAction(int $productId): array
    {
        return FavoriteService::add($productId);
    }

    public function removeAction(int $productId): array
    {
        return FavoriteService::remove($productId);
    }

    public function clearAction(): array
    {
        return FavoriteService::clear();
    }

    public function shareAction(): array
    {
        return FavoriteService::share();
    }
}