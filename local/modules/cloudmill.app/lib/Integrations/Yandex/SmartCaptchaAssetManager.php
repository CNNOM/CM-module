<?php
declare(strict_types=1);

namespace CloudMill\App\Integrations\Yandex;

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Page\AssetLocation;
use CloudMill\App\Basket\Service\ServiceProvider;

final class SmartCaptchaAssetManager
{
    private static bool $alreadyIncluded = false;
    private string $inputSelector = '_captcha_token';

    public function appendScripts(): void
    {
        if (self::$alreadyIncluded) return;

        $callMethod = 'ya_' . uniqid();

        $client = ServiceProvider::SmartCaptchaClient();
        $appendJs = <<<HTML
            <script>
              function {$callMethod}() {
                if (!window.smartCaptcha) {
                  return;
                }
                
                const inputs = document.querySelectorAll('input[name="{$this->inputSelector}"]');
                
                inputs.forEach((element) => {
                   element.resetCaptcha = () => {
                      element.dataset.widgetId = window.smartCaptcha.render(element, {
                          sitekey: '{$client->getClientKey()}',
                          invisible: true,
                          hideShield: true,
                          callback: (token) => {
                            element.value = token;
                            window.smartCaptcha.destroy(element.dataset.widgetId);
                            element.resetCaptcha();
                          },
                       });   
                   }
                   
                   element.resetCaptcha();
                });
              }
            </script>
        HTML;

        Asset::getInstance()->addString(
            sprintf('<script src="https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=%s" defer></script>', $callMethod)
        );

        Asset::getInstance()->addString($appendJs, AssetLocation::BODY_END);

        self::$alreadyIncluded = true;
    }

    public function getInput(): string
    {
        return <<<HTML
            <input type="hidden" name="{$this->inputSelector}" data-widget-id>
        HTML;
    }
}
