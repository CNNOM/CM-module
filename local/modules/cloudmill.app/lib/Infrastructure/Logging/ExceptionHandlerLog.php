<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Logging;

use Bitrix\Main\Diag\FileExceptionHandlerLog;
use Psr\Log\LoggerInterface;

/**
 * Класс для работы с непойманными ошибками
 */
final class ExceptionHandlerLog extends FileExceptionHandlerLog
{
    private null|Logger|LoggerInterface $additionalLogger = null;

    /**
     * Регистрация логгера для ExceptionHandler битрикса
     */
    public static function register(string $logDir, null|Logger|LoggerInterface $additionalLogger): void
    {
        $logDir = str_ends_with($logDir, '/') ? $logDir : $logDir . '/';

        if (!file_exists($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            \CEventLog::Add([
                "SEVERITY" => "ERROR",
                "AUDIT_TYPE_ID" => "LOGGER_ERROR",
                "MODULE_ID" => "CloudMill",
                "ITEM_ID" => $logDir,
                "DESCRIPTION" => "Can't create logs directory",
            ]);
            return;
        }

        $logFile = sprintf('%s%s.log', $logDir, 'exceptions-' . date('Y-m-d'));
        if (!file_exists($logFile)) {
            self::cleanupOldEmptyLogs($logDir);
            file_put_contents($logFile, '');
            chmod($logFile, 0644);
        }

        $config = \Bitrix\Main\Config\Configuration::getInstance();
        $configItem = $config->get('exception_handling');
        $configItem['log'] = [
            'class_name' => __CLASS__,
            'required_file' => __FILE__,
            'settings' => [
                'file' => $logFile,
                'additionalLogger' => $additionalLogger,
            ]
        ];

        $config->add('exception_handling', $configItem);
    }

    private static function cleanupOldEmptyLogs(string $logDir): void
    {
        $files = glob("{$logDir}exceptions-*.log") ?: [];
        
        foreach ($files as $file) {
            if (filesize($file) === 0) {
                unlink($file);
            }
        }
    }

    /**
     * Родительский конструктор инстанса класса
     */
    public function initialize(array $options): void
    {
        parent::initialize($options);

        if (isset($options["additionalLogger"])) {
            $this->additionalLogger = $options["additionalLogger"];
        }
    }

    public function write($exception, $logType): void
    {
        parent::write($exception, $logType);

        $this->additionalLogger->log(\CEventLog::SEVERITY_CRITICAL, $exception);
    }

    /**
     * Функция отключает логирование непойманных ошибок
     */
    public static function disableLogger(): void
    {
        $config = \Bitrix\Main\Config\Configuration::getInstance();
        $configItem = $config->get('exception_handling');
        $configItem['log'] = [];
        $config->add('exception_handling', $configItem);
    }
}
