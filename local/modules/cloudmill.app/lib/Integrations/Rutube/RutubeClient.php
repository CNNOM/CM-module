<?php
declare(strict_types=1);

namespace CloudMill\App\Integrations\Rutube;

use Bitrix\Main\SystemException;
use Bitrix\Main\Web\Json;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;
use CloudMill\App\Infrastructure\Http\BitrixHttpTransport;

final class RutubeClient
{
    private const API_BASE_URL = 'https://rutube.ru/api/play/options/';
    private const EMBED_URL_PREFIX = 'https://rutube.ru/play/embed/';
    private const EMBED_PATTERN = '/\/embed\/([^\/]+)\//';
    private const VIDEO_PATTERN = '/\/video\/([^\/]+)\//';

    public static function getVideoByLink(string $link): ?RutubeVideoDto
    {
        $videoId = self::extractVideoIdFromUrl($link);

        if (empty($videoId)) {
            return null;
        }

        $response = self::fetchVideoData($videoId);
        if (!$response) {
            return null;
        }

        return self::getRutubeVideoDto($response, $link);
    }

    private static function extractVideoIdFromUrl(string $url): ?string
    {
        $lowerUrl = strtolower($url);
        $pattern = str_contains($lowerUrl, 'embed') ? self::EMBED_PATTERN : self::VIDEO_PATTERN;

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    private static function fetchVideoData(string $videoId): array
    {
        $response = (new BitrixHttpTransport())->get(self::API_BASE_URL . $videoId . '/');

        try {
            if ($response['status'] !== 200) {
                $message = sprintf(
                    'Rutube API error: %s. Status: %d', Json::encode($response['errors']), $response['status']
                );
                throw new SystemException($message);
            }

            return Json::decode($response['body']);
        } catch (SystemException $e) {
            ExceptionHandler::handle($e);
        }

        return [];
    }

    private static function getRutubeVideoDto($response, $link): RutubeVideoDto
    {
        return new RutubeVideoDto(
            id: $response['video_id'],
            name: $response['title'],
            rawLink: $link,
            link: self::EMBED_URL_PREFIX . $response['video_id'] . '/',
            picJpg: $response['thumbnail_url'],
            duration: $response['duration'],
        );
    }
}
