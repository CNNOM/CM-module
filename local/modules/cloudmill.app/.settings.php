<?php

use CloudMill\App\Basket\Service\BasketService;
use CloudMill\App\Integrations\Yandex\SmartCaptchaClient;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use CloudMill\App\Infrastructure\Settings\PageSettings;

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
            'cloudmill.' . SmartCaptchaClient::class => [
                'constructor' => static function () {
                    $url = 'https://smartcaptcha.yandexcloud.net/validate';
                    $publicKey = PageSettings::get('CAPTCHA_PUBLIC_KEY');
                    $privateKey = PageSettings::get('CAPTCHA_PRIVATE_KEY');
                    return new SmartCaptchaClient($url, $publicKey, $privateKey);
                }
            ]
        ],
    ],
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\CloudMill\\App',
        ],
        'readonly' => true,
    ],
];
