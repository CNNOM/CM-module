<?php
declare(strict_types=1);

namespace CloudMill\App\Compare\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use CloudMill\App\Compare\Service\UserListManager;
use CloudMill\App\Compare\Service\CatalogCompareService;

final class UserListController extends Controller
{
    private const COMPARE_LIMIT = 20;

    public function configureActions(): array
    {
        return [
            'handle' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
        ];
    }

    public function handleAction(
        string $userAction,
        string $order = 'add',
        array $prodIDs = [],
        ?string $section = null,
        ?string $page = null
    ): ?array {
        if (!UserListManager::isValidAction($userAction)) {
            $this->addError(new Error('Unsupported user action'));
            return null;
        }

        $storage = UserListManager::getStorage($userAction);
        $compareService = new CatalogCompareService();
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $prodIDs), static fn (int $id): bool => $id > 0)));

        if ($userAction === UserListManager::ACTION_COMPARE) {
            $itemIds = $compareService->normalizeCompareIds($itemIds);
        }

        if ($order === 'delete') {
            $storage->removeItems($itemIds);
        } else {
            if (
                $userAction === UserListManager::ACTION_COMPARE
                && $storage->count() + count(array_diff($itemIds, $storage->get())) > self::COMPARE_LIMIT
            ) {
                $this->addError(new Error('Список сравнения заполнен'));
                return null;
            }

            $storage->addItems($itemIds);
        }

        return $this->makeResponse(
            userAction: $userAction,
            storage: $storage,
            section: $section,
            page: $page,
            compareService: $compareService
        );
    }

    private function makeResponse(
        string $userAction,
        $storage,
        ?string $section,
        ?string $page,
        CatalogCompareService $compareService
    ): array {
        $currentItems = $storage->get();
        $response = [
            'userAction' => $userAction,
            'changes' => $storage->getChanges(),
            'current_items' => $currentItems,
            'ui_items' => $currentItems,
            'html' => [],
            'newUrl' => null,
            'activeSection' => null,
            'page' => $page,
        ];

        if ($userAction === UserListManager::ACTION_COMPARE) {
            $pageData = $compareService->getPageData($currentItems, $section);
            $activeSection = $pageData['activeSection']['CODE'] ?? null;
            $response['activeSection'] = $activeSection;
            $response['ui_items'] = $compareService->getUiItemIds($currentItems);
            $response['newUrl'] = $activeSection ? '/compare/' . rawurlencode($activeSection) . '/' : '/compare/';
            if ($pageData['isEmpty']) {
                $response['newUrl'] = '/compare/';
            }
        }

        return $response;
    }
}
