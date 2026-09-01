<?php
declare(strict_types=1);

namespace CloudMill\App\Search\Service;

use Bitrix\Iblock\Component\Tools;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\IblockManager;
use CloudMill\App\Catalog\Model\Book;
use CloudMill\App\Infrastructure\Bitrix\Context\CurrentDirectory;

final class HistoryBookService
{
    public ?Book $book = null;
    public string $elementCode = '';
    private CurDir $curDir;
    private string $iblockCode;

    public function __construct(CurDir $curDir, string $IBlockBookCode)
    {
        $this->curDir = $curDir;
        $this->iblockCode = $IBlockBookCode;
        $this->resolve();
    }

    private function resolve(): void
    {
        $bookCode = $this->curDir->getByKey(4);
        $this->elementCode = $this->curDir->getByKey(6);

        if (!$bookCode) {
            $this->redirect();
            return;
        }

        $this->book = $this->loadBook($bookCode);
        if (!$this->book) {
            Tools::process404(showPage: true);
            return;
        }

        if (!$this->elementCode) {
            $this->redirect();
        }
    }

    private function redirect(): void
    {
        switch ($this->curDir->lastKey) {
            case 1:
            case 2:
            case 3:
                IblockManager::redirectToFirstSection($this->iblockCode);
                break;
            case 4:
                IblockManager::redirectToFirstSection($this->iblockCode, $this->curDir->last);
                break;
            case 5:
                IblockManager::redirectToFirstElement($this->iblockCode, $this->curDir->last);
                break;
            case 6:
                break;
            default:
                LocalRedirect('/404.php');
        }
    }

    private function loadBook(string $code): ?Book
    {
        $section = \CIBlockSection::GetList(
            [],
            [
                'IBLOCK_ID' => IblockManager::getID($this->iblockCode),
                'CODE' => $code,
                'ACTIVE' => 'Y',
                'DEPTH_LEVEL' => 1,
            ],
            false,
            ['ID', 'NAME', 'CODE', 'PICTURE', 'UF_*']
        )->GetNext();

        if (!$section) {
            return null;
        }

        $userFields = array_filter(
            $section,
            static fn($key) => str_starts_with((string)$key, 'UF_'),
            ARRAY_FILTER_USE_KEY
        );

        return new Book(
            id: (int)$section['ID'],
            name: $section['NAME'],
            code: $section['CODE'],
            picture: $section['PICTURE'] ?: null,
            userFields: $userFields,
        );
    }
}
