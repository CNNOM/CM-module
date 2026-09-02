<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

class DetailViewer
{
    private array $fileArray;

    private array $componentMap = [
        'photo'  => 'cloudmill:detail.photo',
        'figure' => 'cloudmill:detail.figure',
        'slider' => 'cloudmill:detail.slider',
    ];

    public function __construct(array $fileArray = [])
    {
        $this->fileArray = $fileArray;
    }

    /**
     * Основной метод парсинга.
     * Сначала обрабатываются парные теги (с закрывающим), затем – самозакрывающиеся.
     */
    public function parse(string $html): string
    {
        $html = htmlspecialchars_decode($html);

        $html = $this->parsePairedTags($html);

        $pattern = '/\[([A-Z_][A-Z0-9_]*)\s+([^\]]+?)\s*\/?\]/i';
        $html = preg_replace_callback($pattern, [$this, 'processTag'], $html);

        return $html;
    }

    /**
     * Обрабатывает парные теги [TYPE ...] ... [/TYPE] с рекурсией (поддерживает вложенные тэги).
     */
    private function parsePairedTags(string $html): string
    {
        $pattern = '~\[([A-Z_][A-Z0-9_]*)(\s+[^\]]*?)?\s*\]((?:(?R)|\[(?!/\1\s*\])|[^\[])*)\[/\1\s*\]~is';

        return preg_replace_callback($pattern, function ($matches) {
            $type = $matches[1];
            $attrString = $matches[2] ?? '';
            $content = $matches[3];

            $processedContent = $this->parse($content);

            $componentName = $this->resolveComponent(strtolower($type));
            if (!$componentName) {
                return '';
            }

            $attributes = $this->extractAttributes($attrString);

            $params = $this->resolveParams($attributes, $componentName);

            $params['content'] = $processedContent;

            return $this->renderComponent($componentName, $params);
        }, $html);
    }

    private function processTag($match): string
    {
        $type = $match[1];
        $componentName = $this->resolveComponent(strtolower($type));
        if (!$componentName) {
            return '';
        }
        $attributes = $this->extractAttributes($match[2]);
        $params = $this->resolveParams($attributes, $componentName);
        return $this->renderComponent($componentName, $params);
    }

    private function extractAttributes(string $attrString): array
    {
        $params = [];
        if (preg_match_all('/(\w+)=(["\'])(.*?)\2/', $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = $match[3];
            }
        }
        return $params;
    }

    private function resolveComponent(string $type): ?string
    {
        return $this->componentMap[$type] ?? null;
    }

    private function resolveParams(array|null $attributes, string $componentName): array
    {
        $params = $attributes ?? [];
        $params['fileArray'] = $this->fileArray;

        return $params;
    }

    private function renderComponent(string $componentName, array $params): string
    {
        ob_start();
        global $APPLICATION;
        $APPLICATION->IncludeComponent(
            $componentName,
            '',
            $params
        );
        return ob_get_clean();
    }
}
