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
        /* для страницы Доставка */
        'paragraph_delivery' => 'cloudmill:detail.paragraph_delivery',
        'grey_text_delivery' => 'cloudmill:detail.grey_text_delivery',
        'text_delivery' => 'cloudmill:detail.text_delivery',
        'list_delivery' => 'cloudmill:detail.list_delivery',
        'list_item_delivery' => 'cloudmill:detail.list_item_delivery',
        'button_delivery' => 'cloudmill:detail.button_delivery',
        'note_delivery' => 'cloudmill:detail.note_delivery',
        /* для страницы Возврат */
        'title_refund' => 'cloudmill:detail.title_refund',
        'info_refund' => 'cloudmill:detail.info_refund',
        'lead_refund' => 'cloudmill:detail.lead_refund',
        'text_refund' => 'cloudmill:detail.text_refund',
        'list_refund' => 'cloudmill:detail.list_refund',
        'list_item_refund' => 'cloudmill:detail.list_item_refund',
        'step_refund' => 'cloudmill:detail.step_refund',
        'step_num_refund' => 'cloudmill:detail.step_num_refund',
        'note_refund' => 'cloudmill:detail.note_refund',
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