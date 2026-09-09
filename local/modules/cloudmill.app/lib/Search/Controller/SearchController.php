<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use CloudMill\App\Search\Service\SearchHistoryService;

final class SearchController extends Controller
{
    public function configureActions(): array
    {
        $filters = [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                new ActionFilter\Csrf(),
            ],
            '-prefilters' => [ActionFilter\Authentication::class],
        ];

        return [
            'addProduct' => $filters,
            'clearHistory' => $filters,
        ];
    }

    public function addProductAction(int $productId): ?array
    {
        if ($productId <= 0) {
            $this->addError(new Error('Некорректный ID товара'));
            return null;
        }

        SearchHistoryService::addProduct($productId);

        return ['success' => true];
    }

    public function clearHistoryAction(): array
    {
        SearchHistoryService::clear();

        return ['success' => true];
    }
}
