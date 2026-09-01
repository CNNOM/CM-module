<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Logging;

use CloudMill\App\Infrastructure\Bitrix\Context\ApplicationContext;
use CloudMill\App\Infrastructure\Logging\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class ExceptionHandler implements LoggerAwareInterface
{
    private static ?self $instance = null;
    public null|LoggerInterface|Logger $logger = null;

    private function __construct(Logger $logger)
    {
        $this->setLogger($logger);
    }

    public static function getInstance(): self
    {
        $logger = new Logger(sendEmailAlert: true, module: 'CloudMill.app', auditType: 'HANDLED_EXCEPTION');
        return self::$instance ??= new self($logger);
    }

    public static function handle(\Exception $exception, LogLevel|string $logLevel = LogLevel::ERROR): void
    {
        // dev only
        if (!ApplicationContext::isProdSite()) {
            throw $exception;
        }

        self::getInstance()->logger->log($logLevel, $exception);
    }

    public static function addToLog($message, LogLevel|string $logLevel = LogLevel::ERROR): void
    {
        self::getInstance()->logger->log($logLevel, $message);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
