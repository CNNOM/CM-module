<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Bitrix\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use CloudMill\App\Infrastructure\Logging\ExceptionHandler;
use \Bitrix\Main\Error;

final class Example extends Controller
{
    protected array $response = [];

    public function configureActions(): array
    {
        return [
            'handle' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ]
        ];
    }

    public function handleAction($param1, $param2): ?array
    {
        // code

        if (0) { // example of error
            $message = 'текст ошибки';
            $this->addError(new Error($message)); // add to response
            ExceptionHandler::addToLog($message); // add to log
        }

        return $this->response;
    }
}
