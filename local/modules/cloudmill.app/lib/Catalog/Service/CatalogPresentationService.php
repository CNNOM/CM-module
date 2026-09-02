<?php
declare(strict_types=1);

namespace CloudMill\App\Catalog\Service;

final class CatalogPresentationService
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    /**
     * Возвращает активные разделы каталога для вывода в виде табов.
     *
     * Может ограничить список дочерними разделами, отметить текущий раздел,
     * заменить URL и добавить вкладку «Все».
     *
     * @param string|null $currentSectionCode Код текущего раздела.
     * @param string|null $parentSectionCode Код родительского раздела.
     * @param string $addParentTab Название дополнительной вкладки «Все».
     * @param string $urlRewrite Шаблон URL с маркером #SECTION_CODE#.
     * @param bool $onlyWithItems Показывать только разделы с товарами.
     * @return array<string|int, array<string, mixed>> Массив табов.
     */
    public function getSectionTabsArray(
        ?string $currentSectionCode,
        ?string $parentSectionCode = null,
        string $addParentTab = '',
        string $urlRewrite = '',
        bool $onlyWithItems = true
    ): array {
        $filter = [
            'GLOBAL_ACTIVE' => 'Y',
        ];

        if ($onlyWithItems) {
            $filter['CNT_ACTIVE'] = true;
        }

        if ($parentSectionCode !== null && $parentSectionCode !== '') {
            $parentSection = $this->catalogService->getSectionByCode($parentSectionCode, [
                'ID',
                'DEPTH_LEVEL',
                'NAME',
            ]);

            if (!$parentSection) {
                return [];
            }

            $filter['DEPTH_LEVEL'] = (int)$parentSection['DEPTH_LEVEL'] + 1;
            $filter['SECTION_ID'] = (int)$parentSection['ID'];
        } else {
            $filter['DEPTH_LEVEL'] = 1;
        }

        $sections = $this->catalogService->getSections(
            filter: $filter,
            limit: 0,
            select: ['ID', 'CODE', 'NAME', 'SECTION_PAGE_URL', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'UF_*'],
            withCount: true
        );

        $activeFound = false;
        $sectionTabs = [];
        foreach ($sections as $section) {
            if ($onlyWithItems && !(int)($section['ELEMENT_CNT'] ?? 0)) {
                continue;
            }

            if ($currentSectionCode === ($section['CODE'] ?? null)) {
                $section['IS_ACTIVE'] = $activeFound = true;
            }

            if ($urlRewrite !== '') {
                $section['SECTION_PAGE_URL'] = str_replace(
                    '#SECTION_CODE#',
                    (string)$section['CODE'],
                    $urlRewrite
                );
            }

            $sectionTabs[$section['ID']] = $section;
        }

        if ($addParentTab !== '' && $sectionTabs) {
            $firstSection = reset($sectionTabs);
            $allUrl = $urlRewrite !== ''
                ? str_replace('#SECTION_CODE#/', '', $urlRewrite)
                : preg_replace(
                    '#/[^/]+/?$#',
                    $parentSectionCode ? '/' . $parentSectionCode . '/' : '/',
                    (string)$firstSection['SECTION_PAGE_URL']
                );

            $sectionTabs = array_merge(['ALL' => [
                'NAME' => $addParentTab,
                'SECTION_PAGE_URL' => $allUrl,
                'IS_ACTIVE' => !$activeFound,
            ]], $sectionTabs);
        }

        return $sectionTabs;
    }
}
