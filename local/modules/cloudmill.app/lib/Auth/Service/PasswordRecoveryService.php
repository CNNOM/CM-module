<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Service;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\Type\DateTime;
use CUser;
use CEvent;
use CloudMill\App\Auth\Dto\PasswordDto;
use CloudMill\App\Infrastructure\Bitrix\DI\ServiceProvider;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

final class PasswordRecoveryService
{
    private const HL_BLOCK_CODE = 'CloudmillAuthPasswordRecovery';
    private const TTL_MINUTES = 30;

    public function requestRecovery(string $login): Result
    {
        $result = new Result();

        $user = $this->findUserByLogin($login);
        if (!$user) {
            // Не раскрываем существование учётной записи.
            return $result;
        }

        $token = bin2hex(random_bytes(32));

        $fields = [
            'UF_USER_ID' => $user['ID'],
            'UF_TOKEN'   => hash('sha256', $token),
            'UF_EMAIL'   => $user['EMAIL'],
            'UF_USED'    => false,
            'UF_DATE_CREATE' => new DateTime(),
            'UF_DATE_EXPIRE' => (new DateTime())->add('+' . self::TTL_MINUTES . ' minutes'),
        ];
        $hlId = $this->saveToHlBlock($fields);
        if (!$hlId) {
            return $result->addError(new Error('Ошибка сохранения токена'));
        }

        $this->sendRecoveryEmail($user['EMAIL'], $token);

        return $result;
    }

    public function resetPassword(string $token, string $newPassword): Result
    {
        $result = new Result();

        $validation = ServiceProvider::ValidationService()->validate(new PasswordDto($newPassword));
        if (!$validation->isSuccess()) {
            foreach ($validation->getErrors() as $error) {
                $result->addError(new Error($error->getMessage()));
            }
            return $result;
        }

        $record = $this->findValidToken($token);
        if (!$record) {
            return $result->addError(new Error('Неверный или истёкший токен'));
        }

        $user = new CUser();
        $updateResult = $user->Update($record['UF_USER_ID'], [
            'PASSWORD' => $newPassword,
            'CONFIRM_PASSWORD' => $newPassword,
        ]);
        if (!$updateResult) {
            return $result->addError(new Error('Ошибка сброса пароля: ' . $user->LAST_ERROR));
        }

        $this->markTokenUsed((int)$record['ID']);

        return $result;
    }

    private function findUserByLogin(string $login): ?array
    {
        $filter = [
            'LOGIC' => 'OR',
            ['=EMAIL' => $login],
            ['=PERSONAL_PHONE' => $login],
        ];
        $row = UserTable::getList([
            'select' => ['ID', 'EMAIL', 'PERSONAL_PHONE'],
            'filter' => $filter,
            'limit' => 1,
        ])->fetch();
        return $row ?: null;
    }

    private function saveToHlBlock(array $fields): ?int
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (!is_string($entity)) return null;

        $result = $entity::add($fields);
        return $result->isSuccess() ? $result->getId() : null;
    }

    private function findValidToken(string $token): ?array
    {
        return HighloadBlockManager::getList(self::HL_BLOCK_CODE, [
            'filter' => [
                '=UF_TOKEN' => hash('sha256', $token),
                '=UF_USED' => false,
                '>=UF_DATE_EXPIRE' => new DateTime(),
            ],
        ])[0] ?? null;
    }

    private function markTokenUsed(int $id): void
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (is_string($entity)) {
            $entity::update($id, ['UF_USED' => true]);
        }
    }

    private function sendRecoveryEmail(string $email, string $token): void
    {
        $resetLink = SITE_DIR . 'reset-password/?token=' . $token;

        CEvent::Send(
            'PASSWORD_RECOVERY',
            SITE_ID,
            [
                'EMAIL' => $email,
                'RESET_LINK' => $resetLink,
            ],
            'Y',
            '',
            ['CONTENT_TYPE' => 'text/html']
        );
    }

    public function validateToken(string $token): bool
    {
        $record = $this->findValidToken($token);
        return (bool)$record;
    }
}
