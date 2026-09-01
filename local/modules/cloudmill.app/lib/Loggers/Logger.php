<?php
declare(strict_types=1);

namespace Cloudmill\App\Loggers;

use Bitrix\Main\Diag\ExceptionHandlerFormatter;
use CloudMill\App\Settings\PageSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class Logger implements LoggerInterface
{
    use LoggerTrait;

    private readonly ?string $module;
    private readonly ?string $auditType;
    private bool $sendEmailAlert;

    public function __construct(bool $sendEmailAlert = true, string $module = null, string $auditType = null)
    {
        $this->sendEmailAlert = $sendEmailAlert;
        $this->module = $module;
        $this->auditType = $auditType;
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        \CEventLog::Add([
            'SEVERITY' => strtoupper($level),
            'AUDIT_TYPE_ID' => $this->auditType,
            'MODULE_ID' => $this->module,
            'DESCRIPTION' => $message
        ]);

        if ($this->sendEmailAlert) {
            $html = $message instanceof \Throwable ? ExceptionHandlerFormatter::format($message, true) : $message;
            $subject = $_SERVER['HTTP_HOST'] . ' ' . $this->auditType;
            $this->sendEmailAlert($html, $subject);
        }
    }

    public function sendEmailAlert(string $html, string $subject): bool
    {
        if(!PageSettings::EXCEPTION_MAIL_TO){
            return true;
        }

        return mail(
            to: PageSettings::EXCEPTION_MAIL_TO,
            subject: $subject,
            message: $html,
            additional_headers: 'Content-type: text/html; charset=utf-8'
        );
    }
}