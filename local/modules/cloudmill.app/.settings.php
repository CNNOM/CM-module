<?php

use Cloudmill\App\Services\BasketService;
use CloudMill\App\Services\YandexSmartCaptchaService;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use CloudMill\App\Settings\PageSettings;

return [
    'services' => [
        'value' => [
            'cloudmill.' . Basket::class => [
                'constructor' => static function (): Basket {
                    foreach (['sale', 'catalog', 'iblock'] as $module) {
                        Loader::includeModule($module);
                    }

                    return Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);
                },
            ],
            'cloudmill.' . BasketService::class => [
                'constructor' => static function (): BasketService {
                    return new BasketService(
                        ServiceLocator::getInstance()->get('cloudmill.' . Basket::class)
                    );
                },
            ],
            'cloudmill.' . YandexSmartCaptchaService::class => [
                'constructor' => static function () {
                    $url = 'https://smartcaptcha.yandexcloud.net/validate';
                    $publicKey = PageSettings::get('CAPTCHA_PUBLIC_KEY');
                    $privateKey = PageSettings::get('CAPTCHA_PRIVATE_KEY');
                    return new YandexSmartCaptchaService($url, $publicKey, $privateKey);
                }
            ]
        ],
    ],
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Cloudmill\\App\\Controller',
        ],
        'readonly' => true,
    ],
];
