<?php

namespace CloudMill\App\Infrastructure\Bitrix\Agents;

use CloudMill\App\Favorites\Service\FavoritesService;

class FavoriteAgent
{
    public static function removeExpiredShares(): string
    {
        FavoritesService::removeExpiredShares();

        return '\\CloudMill\\App\\Infrastructure\\Bitrix\\Agents\\FavoriteAgent::removeExpiredShares();';
    }
}
