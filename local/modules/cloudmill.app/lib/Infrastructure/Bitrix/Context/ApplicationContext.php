<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Context;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Connection;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
final class ApplicationContext
{
    private static HttpRequest $request;
    private static HttpResponse $response;
    private static CurDir $curDir;
    public static array $sharedData = []; // пользовательские данные сюда

    public static function getCurDir(): CurDir
    {
        return self::$curDir ??= new CurDir();
    }

    public static function getRequest(): HttpRequest
    {
        return self::$request ??= Application::getInstance()->getContext()->getRequest();
    }

    public static function getResponse(): HttpResponse
    {
        return self::$response ??= Application::getInstance()->getContext()->getResponse();
    }

    public static function isProdSite(): bool
    {
        return !str_contains($_SERVER['HTTP_HOST'] ?? '', 'devmill');
    }

    public static function isAjax(string $ajaxID = '', string $paramName = 'ajaxID'): bool
    {
        if (!self::getRequest()->isAjaxRequest()) {
            return false;
        }

        if ($ajaxID !== '') {
            return $_REQUEST[$paramName] === $ajaxID;
        }

        return true;
    }

    public static function getDB(): Connection|\Bitrix\Main\DB\Connection
    {
        return Application::getConnection();
    }
}
