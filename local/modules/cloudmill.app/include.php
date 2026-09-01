<?php

// uncaught exception handler logger
use Cloudmill\App\Loggers\ExceptionHandlerLog;
use Cloudmill\App\Loggers\Logger;

ExceptionHandlerLog::register(
    logDir: __DIR__ . '/logs/',
    additionalLogger: new Logger(sendEmailAlert: true, module: 'Cloudmill.app', auditType: 'UNHANDLED_EXCEPTION')
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
