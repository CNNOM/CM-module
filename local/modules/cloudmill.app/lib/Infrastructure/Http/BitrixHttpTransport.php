<?php
declare(strict_types=1);

namespace CloudMill\App\Infrastructure\Http;

use Bitrix\Main\Web\HttpClient;

final class BitrixHttpTransport
{
    public function get(string $url): array
    {
        $client = new HttpClient();
        $client->setHeader('Content-Type', 'application/json');
        $client->setHeader('Accept', 'application/json');

        $response = $client->get($url);

        return [
            'status' => $client->getStatus(),
            'body' => (string)$response,
            'errors' => $client->getError(),
        ];
    }
}
