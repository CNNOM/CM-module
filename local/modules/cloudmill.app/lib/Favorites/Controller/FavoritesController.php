<?php

namespace CloudMill\App\Favorites\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use CloudMill\App\Favorites\Service\FavoritesService;

final class FavoritesController extends Controller
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
        return FavoritesService::add($productId);
    }

    public function removeAction(int $productId): array
    {
        return FavoritesService::remove($productId);
    }

    public function clearAction(): array
    {
        return FavoritesService::clear();
    }

    public function shareAction(): array
    {
        return FavoritesService::share();
    }
}