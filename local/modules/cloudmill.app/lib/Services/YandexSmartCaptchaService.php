<?php
declare(strict_types=1);

namespace CloudMill\App\Services;

use Bitrix\Main\Web\HttpClient;


final class YandexSmartCaptchaService
{
    private string $baseUrl;
    private string $clientKey;
    private string $secretKey;
    private const DEFAULT_TIMEOUT = 10;

    public function __construct(string $baseUrl, string $clientKey, string $secretKey)
    {
        $this->baseUrl = $baseUrl;
        $this->clientKey = $clientKey;
        $this->secretKey = $secretKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function validateToken(string $token): bool
    {
        if (mb_strlen($token) <= 0) {
            return false;
        }

        $client = new HttpClient();
        $client->setTimeout(self::DEFAULT_TIMEOUT);

        $query = [
            'secret' => $this->getSecretKey(),
            'token' => $token,
        ];

        $response = $client->get($this->getBaseUrl() . '?' . http_build_query($query));

        $isValidJson = false;
        if (is_string($response)) {
            if (function_exists('json_validate')) {
                $isValidJson = json_validate($response);
            } else {
                json_decode($response, true);
                $isValidJson = json_last_error() === JSON_ERROR_NONE;
            }
        }

        if ($isValidJson) {
            $data = json_decode($response, true);

            if ($data['status'] === 'ok') {
                return true;
            }

            if ($data['status'] === 'failed') {
                return false;
            }
        }

        return true;
    }

}
