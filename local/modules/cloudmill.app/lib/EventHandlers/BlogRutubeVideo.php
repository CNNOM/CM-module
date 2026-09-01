<?php
declare(strict_types=1);

namespace Cloudmill\App\EventHandlers;

use Bitrix\Main\SystemException;
use CFile;
use CIBlockElement;
use Cloudmill\App\Dto\RutubeVideoDto;
use Cloudmill\App\ExceptionHandlers\AppExceptionHandler;
use Cloudmill\App\Helpers\IBlockHelper;
use Cloudmill\App\Http\RutubeApi;

final class BlogRutubeVideo
{
    private const VIDEO_BLOG_IBLOCK_CODE = 'video_rutube';
    private const PROPERTY_RAW_LINK = 'RAW_LINK';
    private const PROPERTY_PIC_JPG = 'PIC_JPG';


    private static bool $handlerDisallow = false;

    public static function addVideoInfo(array &$fields): void
    {
        if (!self::isValidRequest($fields)) return;

        $elementData = self::getElementData((int)$fields['ID']);

        if (self::shouldProcessElement($elementData)) {
            self::processVideoInfo($fields, $elementData);
        }
    }

    private static function isValidRequest(array $fields): bool
    {
        return !self::$handlerDisallow && $fields['IBLOCK_ID'] == IBlockHelper::getID(self::VIDEO_BLOG_IBLOCK_CODE);
    }

    private static function getElementData(int $elementId): array
    {
        return CIBlockElement::GetList(
            arFilter: ['ID' => $elementId, 'IBLOCK_ID' => IBlockHelper::getID(self::VIDEO_BLOG_IBLOCK_CODE)],
            arSelectFields: ['ID', 'NAME', 'PROPERTY_' . self::PROPERTY_RAW_LINK, 'PROPERTY_' . self::PROPERTY_PIC_JPG]
        )->GetNext() ?: [];
    }

    private static function shouldProcessElement(array $elementData): bool
    {
        return $elementData &&
            empty($elementData['PROPERTY_' . self::PROPERTY_PIC_JPG . '_VALUE']) &&
            !empty($elementData['PROPERTY_' . self::PROPERTY_RAW_LINK . '_VALUE']);
    }

    private static function processVideoInfo(array $fields, array $elementData): void
    {
        $videoDto = RutubeApi::getVideoByLink($elementData['PROPERTY_' . self::PROPERTY_RAW_LINK . '_VALUE']);
        if (!$videoDto) return;

        self::$handlerDisallow = true;
        self::updateElement($fields['ID'], $videoDto);
        self::updateElementProperties($fields, $videoDto);
        self::$handlerDisallow = false;
    }

    private static function updateElement(int $elementId, RutubeVideoDto $videoDto): void
    {
        if (!$videoDto->name) return;

        $elem = new CIBlockElement();
        $isUpdated = $elem->Update($elementId, ['NAME' => $videoDto->name]);

        if (!$isUpdated) {
            AppExceptionHandler::handle(new SystemException($elem->getLastError()));
        }
    }

    private static function updateElementProperties(array $fields, RutubeVideoDto $videoDto): void
    {
        $arr = self::mapVideoDtoToProperties($videoDto);
        if (!$arr) return;

        unset($arr['NAME']);
        if ($arr['PIC_JPG']) {
            $fileArr = CFile::MakeFileArray($arr['PIC_JPG']);
            $arr['PIC_JPG'] = ['VALUE' => $fileArr, 'DESCRIPTION' => ''];
        }
        if ($arr['DURATION']) {
            $sec = intval($arr['DURATION'] / 1000);
            $duration = gmdate("H:i:s", $sec);
            $arr['DURATION'] = $duration;
        }

        foreach ($arr as $k => $v) {
            CIBlockElement::SetPropertyValuesEx($fields['ID'], $fields['IBLOCK_ID'], [$k => $v]);
        }
    }

    private static function mapVideoDtoToProperties(RutubeVideoDto $videoDto): array
    {
        return array_filter([
            'ID' => $videoDto->id,
            'NAME' => $videoDto->name,
            'RAW_LINK' => $videoDto->rawLink,
            'LINK' => $videoDto->link,
            'PIC_JPG' => $videoDto->picJpg,
            'DURATION' => $videoDto->duration,
            'HOST' => $videoDto->host,
        ], static fn($value) => $value !== null && $value !== '');
    }
}
