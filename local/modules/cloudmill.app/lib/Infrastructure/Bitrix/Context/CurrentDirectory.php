<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Context;

use Bitrix\Main\Application;

final class CurrentDirectory
{
    private array $explodedDir;
    public string $last;
    public int $lastKey;
    public string $parent;
    public int $parentKey;

    public function __construct()
    {
        $this->explodedDir = $this->parseCurrentDirectory();
        $this->initializeProperties();
    }

    private function parseCurrentDirectory(): array
    {
        $application = Application::getInstance();
        $currentDir = $application->getContext()->getRequest()->getRequestedPageDirectory();

        return array_filter(explode('/', $currentDir), fn(string $part) => $part !== '');
    }

    private function initializeProperties(): void
    {
        $this->last = (string)end($this->explodedDir);
        $this->lastKey = (int)array_key_last($this->explodedDir);

        $this->parent = $this->explodedDir[$this->lastKey - 1] ?? $this->last;
        $this->parentKey = max(0, $this->lastKey - 1);
    }

    public function getArray(): array
    {
        return $this->explodedDir;
    }

    public function getByKey(int $key): string
    {
        return $this->explodedDir[$key] ?? '';
    }

    /**
     * Возможность переопределять директории
     * @param int $key
     * @param string $name
     * @return void
     */
    public function setByKey(int $key, string $name): void
    {
        $this->explodedDir[$key] = $name;
    }
}
