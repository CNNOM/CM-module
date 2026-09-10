<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\ActionFilters;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use CloudMill\App\Infrastructure\Bitrix\DI\ServiceProvider;
use CloudMill\App\Integrations\Yandex\SmartCaptchaClient;

class YandexSmartCaptcha extends Base
{
    private const TOKEN_NOT_VALID = 'TOKEN_NOT_VALID';
    private SmartCaptchaClient $client;
    private string $bodyFieldName;

    public function __construct(string $bodyFieldName)
    {
        $this->client = ServiceProvider::SmartCaptchaClient();
        $this->bodyFieldName = $bodyFieldName;

        parent::__construct();
    }

    public function onBeforeAction(Event $event): ?EventResult
    {
        $token = (string)$this->getAction()->getController()->getRequest()->get($this->bodyFieldName);

        if ($this->client->validateToken($token)) {
            return null;
        }

        $this->addError(new Error('Система посчитала вам роботом. Сохраните данные, перезагрузите страницу и попробуйте еще раз', self::TOKEN_NOT_VALID));
        return new EventResult(EventResult::ERROR, null, null, $this);
    }
}
