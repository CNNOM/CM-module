<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Presentation;

final class SeoService
{
    /**
     * Устанавливает SEO-данные страницы из IPROPERTY_VALUES или названия.
     *
     * @param string $name Резервное название страницы.
     * @param array<string, mixed>|null $ipropValues Значения SEO-свойств Bitrix.
     */
    public static function setItemMeta(string $name, ?array $ipropValues = []): void
    {
        global $APPLICATION;

        $entity = 'ELEMENT';
        if (!empty($ipropValues)) {
            $firstKey = array_key_first($ipropValues);
            if (preg_match('/^(SECTION|ELEMENT)_/', (string)$firstKey, $matches)) {
                $entity = $matches[1];
            }
        }

        if (isset($ipropValues[$entity . '_META_TITLE'])) {
            $APPLICATION->SetPageProperty('title', $ipropValues[$entity . '_META_TITLE'] ?? $name);
        } elseif ($name) {
            $APPLICATION->SetPageProperty('title', $name);
        }

        if (isset($ipropValues[$entity . '_PAGE_TITLE'])) {
            $APPLICATION->SetTitle($ipropValues[$entity . '_PAGE_TITLE']);
        } elseif ($name) {
            $APPLICATION->SetTitle($name);
        }

        if (isset($ipropValues[$entity . '_META_KEYWORDS'])) {
            $APPLICATION->SetPageProperty('KEYWORDS', $ipropValues[$entity . '_META_KEYWORDS']);
        }

        if (isset($ipropValues[$entity . '_META_DESCRIPTION'])) {
            $APPLICATION->SetPageProperty('DESCRIPTION', $ipropValues[$entity . '_META_DESCRIPTION']);
        }
    }
}
