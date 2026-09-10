<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Storage;

use Bitrix\Main\Type\DateTime;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

final class SharedListStorage
{
    public function __construct(
        private readonly string $hlCode,
        private readonly int $limit,
        private readonly string $ttl = '+7 days',
    ) {
    }

    public function share(array $items): array
    {
        $items = CookieListStorage::normalizeIds($items, $this->limit);
        if (!$items) {
            return [
                'success' => false,
                'code' => 'EMPTY_LIST',
                'error' => 'List is empty',
            ];
        }

        sort($items);
        $hash = md5(json_encode($items));

        $saved = HighloadBlockManager::getList(
            $this->hlCode,
            ['select' => ['ID', 'UF_HASH'], 'filter' => ['UF_HASH' => $hash], 'limit' => 1]
        )[0] ?? null;

        $entity = HighloadBlockManager::getEntity($this->hlCode);
        if (!$entity) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND_HL',
                'error' => 'HL Share not found',
            ];
        }

        $fields = ['UF_EXPIRED_DATE' => (new DateTime())->add($this->ttl)];
        $result = $saved !== null
            ? $entity::update((int)$saved['ID'], $fields)
            : $entity::add($fields + [
                'UF_HASH' => $hash,
                'UF_PRODUCTS' => json_encode($items),
            ]);

        if (!$result->isSuccess()) {
            return [
                'success' => false,
                'code' => 'SAVE_ERROR',
                'error' => implode('; ', $result->getErrorMessages()),
            ];
        }

        return [
            'success' => true,
            'code' => 'SHARED',
            'hash' => $hash,
            'result' => $saved !== null ? 'exist' : 'new',
        ];
    }

    public function getItems(string $hash): array
    {
        if ($hash === '') {
            return [];
        }

        $record = HighloadBlockManager::getList(
            $this->hlCode,
            ['filter' => ['UF_HASH' => $hash, '>UF_EXPIRED_DATE' => new DateTime()], 'limit' => 1]
        )[0] ?? null;

        if (!$record) {
            return [];
        }

        $items = json_decode((string)$record['UF_PRODUCTS'], true);

        return CookieListStorage::normalizeIds(is_array($items) ? $items : [], $this->limit);
    }

    public function removeExpired(): int
    {
        $expired = HighloadBlockManager::getList(
            $this->hlCode,
            ['select' => ['ID'], 'filter' => ['<=UF_EXPIRED_DATE' => new DateTime()]]
        );

        if (empty($expired)) {
            return 0;
        }

        $entity = HighloadBlockManager::getEntity($this->hlCode);
        if (!$entity) {
            return 0;
        }

        $deleted = 0;
        foreach ($expired as $item) {
            $result = $entity::delete((int)$item['ID']);
            if ($result->isSuccess()) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
