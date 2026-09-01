<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use CloudMill\App\Compare\Service\CompareService;

final class CompareController extends Controller
{
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
            'add' => $commonFilters,
            'remove' => $commonFilters,
            'clear' => $commonFilters,
            'share' => $commonFilters,
        ];
    }

    public function addAction(int $productId): array
    {
        return CompareService::add($productId);
    }

    public function removeAction(int $productId): array
    {
        return CompareService::remove($productId);
    }

    public function clearAction(): array
    {
        return CompareService::clear();
    }

    public function shareAction(): array
    {
        return CompareService::share();
    }
}
