<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class cloudmill_app extends CModule
{
    public static function getModuleId(): string
    {
        return basename(dirname(__DIR__));
    }

    public function __construct()
    {
        $this->MODULE_ID = 'cloudmill.app';
        $this->MODULE_VERSION = "1.0.0";
        $this->MODULE_VERSION_DATE = "2025-01-01 00:00:00";
        $this->MODULE_NAME = "Cloudmill: Основной модуль";
        $this->MODULE_DESCRIPTION = "Модуль содержит кастомные классы и функции, обработчики событий, агенты";
        $this->PARTNER_NAME = "cloudmill";
        $this->PARTNER_URI = "https://cloudmill.ru";
    }

    public function doInstall(): bool
    {
        try {
            \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);
            $this->installAgent();
        } catch (Exception $e) {
            global $APPLICATION;
            $APPLICATION->ThrowException($e->getMessage());

            return false;
        }

        return true;
    }

    public function doUninstall(): bool
    {
        try {
            \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);
            $this->uninstallAgent();
        } catch (Exception $e) {
            global $APPLICATION;
            $APPLICATION->ThrowException($e->getMessage());

            return false;
        }

        return true;
    }

    private function installAgent(): void
    {
        $agentName = '\\Cloudmill\\App\\Agents\\FavoriteAgent::removeExpiredShares();';

        // Проверяем, существует ли уже агент
        $existingAgent = \CAgent::GetList(
            ['ID' => 'DESC'],
            ['NAME' => $agentName]
        )->Fetch();

        if (!$existingAgent) {
            \CAgent::AddAgent(
                $agentName,                // имя агента
                $this->MODULE_ID,          // модуль
                'Y',                       // периодический (интервал считается от предыдущего NEXT_EXEC)
                86400,                     // период: 24 часа
                '',                        // дата первого запуска (сейчас)
                'Y',                       // активен
                date("d.m.Y H:i:s", strtotime("+1 minute")), // запуск через минуту
                100                        // сортировка
            );
        }
    }

    private function uninstallAgent(): void
    {
        \CAgent::RemoveAgent('\\Cloudmill\\App\\Agents\\FavoriteAgent::removeExpiredShares();', $this->MODULE_ID);
    }
}
