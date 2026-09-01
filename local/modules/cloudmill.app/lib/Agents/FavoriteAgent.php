<?php

namespace Cloudmill\App\Agents;

use Cloudmill\App\Services\FavoriteService;

class FavoriteAgent
{
    public static function removeExpiredShares(): string
    {
        FavoriteService::removeExpiredShares();

        return '\\Cloudmill\\App\\Agents\\FavoriteAgent::removeExpiredShares();';
    }
}
