<?php
declare(strict_types=1);

namespace Cloudmill\App\ActionFilter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use CloudMill\App\Services\ServiceProvider;
use CloudMill\App\Services\YandexSmartCaptchaService;

class YandexSmartCaptcha extends Base
{
    private const TOKEN_NOT_VALID = 'TOKEN_NOT_VALID';
    private YandexSmartCaptchaService $client;
    private string $bodyFieldName;

    public function __construct(string $bodyFieldName)
    {
        $this->client = ServiceProvider::YandexSmartCaptchaService();
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
