<?php

// uncaught exception handler logger
use CloudMill\App\Infrastructure\Logging\ExceptionHandlerLog;
use CloudMill\App\Infrastructure\Logging\Logger;

ExceptionHandlerLog::register(
    logDir: __DIR__ . '/logs/',
    additionalLogger: new Logger(sendEmailAlert: true, module: 'CloudMill.app', auditType: 'UNHANDLED_EXCEPTION')
);

function dd($data)
{
    global $APPLICATION;
    $APPLICATION->RestartBuffer();
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    die();
}
