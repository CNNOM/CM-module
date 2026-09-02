<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use CloudMill\App\Search\Service\SearchService;

final class SearchController extends Controller
{
    public function configureActions(): array
    {
        return [
            'searchProducts' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'clearHistory' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                    ActionFilter\Csrf::class,
                ],
            ],
        ];
    }

    public function searchProductsAction(SearchService $searchService, string $q = '', int $limit = 6): ?array
    {
        try {
            return $searchService->searchProducts($q, $limit);
        } catch (\Throwable $exception) {
            $this->addError(new Error($exception->getMessage()));
            return null;
        }
    }

    public function clearHistoryAction(SearchService $searchService): ?array
    {
        try {
            $searchService->clearHistory();
        } catch (\Throwable $exception) {
            $this->addError(new Error($exception->getMessage()));
            return null;
        }

        return [
            'success' => true,
        ];
    }
}
