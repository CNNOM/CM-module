<?php

namespace CloudMill\App\Auth\Service;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\Type\DateTime;
use CUser;
use CEvent;
use CloudMill\App\Infrastructure\Bitrix\Wrappers\HighloadBlockManager;

final class RegistrationService
{
    private const HL_BLOCK_CODE = 'CloudmillAuthRegistration';
    private const TTL_MINUTES = 5;

    public function startRegistration(array $data, string $userType): Result
    {
        $result = new Result();

        $existingUser = $this->findUserByEmailOrPhone($data['email'], $data['phone']);

        if ($existingUser) {
            if ($existingUser['ACTIVE'] === 'Y') {
                return $result->addError(new Error('Этот email или телефон уже зарегистрированы'));
            }

            $userId = $existingUser['ID'];
            $user = new CUser();
            $updateFields = [
                'NAME' => $data['name'],
                'PERSONAL_PHONE' => $data['phone'],
                'PHONE_NUMBER' => $data['phone'],
                'PASSWORD' => $data['password'],
                'CONFIRM_PASSWORD' => $data['password'],
            ];
            if ($userType === 'yur') {
                $updateFields['UF_INN'] = $data['inn'] ?? '';
                $updateFields['UF_COMPANY_NAME'] = $data['companyName'] ?? '';
            }
            if (!$user->Update($userId, $updateFields)) {
                return $result->addError(new Error('Ошибка обновления пользователя: ' . $user->LAST_ERROR));
            }

            $pending = $this->findActivePendingByUserId($userId);
            $code = random_int(100000, 999999);

            if ($pending) {
                $this->updatePendingCode($pending['ID'], $code, 'email');
                $this->updatePendingFields($pending['ID'], [
                    'UF_NAME' => $data['name'],
                    'UF_PHONE' => $data['phone'],
                    'UF_EMAIL' => $data['email'],
                    'UF_USER_TYPE' => $userType,
                ]);
                $pendingId = $pending['ID'];
            } else {
                $pendingId = $this->createHlRecord($userId, $data, $userType, $code);
                if (!$pendingId) {
                    return $result->addError(new Error('Не удалось сохранить данные для подтверждения'));
                }
            }
        } else {
            $user = new CUser();
            $userFields = [
                'LOGIN'    => $data['email'],
                'EMAIL'    => $data['email'],
                'NAME'     => $data['name'],
                'PASSWORD' => $data['password'],
                'CONFIRM_PASSWORD' => $data['password'],
                'PHONE_NUMBER' => $data['phone'],
                'PERSONAL_PHONE' => $data['phone'],
                'ACTIVE'   => 'N',
            ];
            if ($userType === 'yur') {
                $userFields['UF_INN'] = $data['inn'] ?? '';
                $userFields['UF_COMPANY_NAME'] = $data['companyName'] ?? '';
            }

            $userId = $user->Add($userFields);
            if (!$userId) {
                return $result->addError(new Error('Ошибка создания пользователя: ' . $user->LAST_ERROR));
            }

            $code = random_int(100000, 999999);
            $pendingId = $this->createHlRecord($userId, $data, $userType, $code);
            if (!$pendingId) {
                CUser::Delete($userId);
                return $result->addError(new Error('Не удалось сохранить данные для подтверждения'));
            }
        }

        $this->sendCodeByEmail($data['email'], $code);

        $result->setData([
            'user_id' => $userId,
            'pending_id' => $pendingId,
        ]);
        return $result;
    }

    public function confirmRegistration(string $email, string $code): Result
    {
        $result = new Result();

        $pending = $this->findPendingRecord($email, $code);
        if (!$pending) {
            return $result->addError(new Error('Неверный код или время истекло'));
        }

        $user = new CUser();
        if (!$user->Update($pending['UF_USER_ID'], ['ACTIVE' => 'Y'])) {
            return $result->addError(new Error('Ошибка активации пользователя: ' . $user->LAST_ERROR));
        }

        $this->updatePendingStatus($pending['ID'], 'confirmed');

        $this->sendNotifications($pending);

        $result->setData(['user_id' => $pending['UF_USER_ID']]);
        return $result;
    }
    public function confirmByPhone(string $phone, string $code): Result
    {
        $result = new Result();

        $pending = $this->findPendingRecordByPhone($phone, $code);
        if (!$pending) {
            return $result->addError(new Error('Неверный код или время истекло'));
        }

        $user = new CUser();
        if (!$user->Update($pending['UF_USER_ID'], ['ACTIVE' => 'Y'])) {
            return $result->addError(new Error('Ошибка активации пользователя: ' . $user->LAST_ERROR));
        }

        $this->updatePendingStatus($pending['ID'], 'confirmed');

        $result->setData(['user_id' => $pending['UF_USER_ID']]);
        return $result;
    }

    public function resendCode(string $email): Result
    {
        $result = new Result();

        $pending = $this->findActivePendingByEmail($email);
        if (!$pending) {
            return $result->addError(new Error('Активная регистрация не найдена'));
        }

        $newCode = random_int(100000, 999999);
        $this->updatePendingCode($pending['ID'], $newCode, 'phone');
        $this->sendCodeBySms($pending['UF_PHONE'], $newCode);

        return $result;
    }

    public function resendCodeToEmail(string $email): Result
    {
        $result = new Result();

        $pending = $this->findActivePendingByEmail($email);
        if (!$pending) {
            return $result->addError(new Error('Активная регистрация не найдена'));
        }

        $newCode = random_int(100000, 999999);
        $this->updatePendingCode($pending['ID'], $newCode, 'email');
        $this->sendCodeByEmail($email, $newCode);

        return $result;
    }

    private function findUserByEmailOrPhone(string $email, string $phone): ?array
    {
        $row = UserTable::getList([
            'select' => [
                'ID',
                'ACTIVE',
                'NAME',
                'EMAIL',
                'PERSONAL_PHONE',
                'LOGIN',
                'UF_INN',
                'UF_COMPANY_NAME',
            ],
            'filter' => [
                'LOGIC' => 'OR',
                ['=EMAIL' => $email],
                ['=PERSONAL_PHONE' => $phone],
            ],
            'limit' => 1,
        ])->fetch();
        return $row ?: null;
    }

    private function findActivePendingByUserId(int $userId): ?array
    {
        return HighloadBlockManager::getList(self::HL_BLOCK_CODE, [
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_STATUS' => 'pending',
                '>=UF_DATE_EXPIRE' => new DateTime(),
            ],
            'limit' => 1
        ])[0] ?? null;
    }

    private function findActivePendingByEmail(string $email): ?array
    {
        return HighloadBlockManager::getList(self::HL_BLOCK_CODE, [
            'filter' => [
                '=UF_EMAIL' => $email,
                '=UF_STATUS' => 'pending',
                '>=UF_DATE_EXPIRE' => new DateTime(),
            ],
            'limit' => 1
        ])[0] ?? null;
    }

    private function findPendingRecord(string $email, string $code): ?array
    {
        return HighloadBlockManager::getList(self::HL_BLOCK_CODE, [
            'filter' => [
                '=UF_EMAIL' => $email,
                '=UF_CONFIRM_CODE' => (int)$code,
                '=UF_STATUS' => 'pending',
                '>=UF_DATE_EXPIRE' => new DateTime(),
            ],
            'limit' => 1
        ])[0] ?? null;
    }

    private function findPendingRecordByPhone(string $phone, string $code): ?array
    {
        return HighloadBlockManager::getList(self::HL_BLOCK_CODE, [
            'filter' => [
                '=UF_PHONE' => $phone,
                '=UF_CONFIRM_CODE' => (int)$code,
                '=UF_STATUS' => 'pending',
                '>=UF_DATE_EXPIRE' => new DateTime(),
            ],
            'limit' => 1
        ])[0] ?? null;
    }

    private function createHlRecord(int $userId, array $data, string $userType, int $code): ?int
    {
        $fields = [
            'UF_USER_ID' => $userId,
            'UF_EMAIL'   => $data['email'],
            'UF_PHONE'   => $data['phone'],
            'UF_NAME'    => $data['name'],
            'UF_USER_TYPE' => $userType,
            'UF_CONFIRM_CODE' => $code,
            'UF_CONFIRM_TYPE' => 'email',
            'UF_STATUS'   => 'pending',
            'UF_DATE_CREATE' => new DateTime(),
            'UF_DATE_EXPIRE' => (new DateTime())->add('+' . self::TTL_MINUTES . ' minutes'),
        ];
        if ($userType === 'yur') {
            $fields['UF_INN'] = $data['inn'] ?? null;
            $fields['UF_COMPANY_NAME'] = $data['companyName'] ?? null;
        }
        return $this->saveToHlBlock($fields);
    }

    private function saveToHlBlock(array $fields): ?int
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (!is_string($entity)) {
            return null;
        }
        $result = $entity::add($fields);
        return $result->isSuccess() ? $result->getId() : null;
    }

    private function updatePendingFields(int $id, array $fields): void
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (!is_string($entity)) {
            return;
        }
        $entity::update($id, $fields);
    }

    private function updatePendingCode(int $id, int $newCode, string $type): void
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (!is_string($entity)) {
            return;
        }
        $entity::update($id, [
            'UF_CONFIRM_CODE' => $newCode,
            'UF_CONFIRM_TYPE' => $type,
            'UF_DATE_EXPIRE' => (new DateTime())->add('+' . self::TTL_MINUTES . ' minutes'),
        ]);
    }

    private function updatePendingStatus(int $id, string $status): void
    {
        $entity = HighloadBlockManager::getEntity(self::HL_BLOCK_CODE);
        if (!is_string($entity)) {
            return;
        }
        $entity::update($id, ['UF_STATUS' => $status]);
    }

    private function sendCodeByEmail(string $email, int $code): void
    {
        CEvent::Send(
            'REGISTRATION_CONFIRM',
            SITE_ID,
            ['EMAIL' => $email, 'CODE' => $code],
            'Y',
            '',
            ['CONTENT_TYPE' => 'text/html']
        );
    }

    private function sendCodeBySms(string $phone, int $code): void
    {
        // FixMe: Реализовать вызов
        // SmsService::send($phone, "Код подтверждения: $code");
    }

    private function sendNotifications(array $row): void
    {
        $existingUser = $this->findUserByEmailOrPhone($row['UF_EMAIL'], $row['UF_PHONE']);

        $this->sendAdminNotification($existingUser);
        $this->sendUserNotification($existingUser);
    }
    private function sendAdminNotification(array $userData): void
    {
        CEvent::Send(
            'NEW_USER_REGISTRED',
            SITE_ID,
            [
                'NAME' => $userData['NAME'],
                'EMAIL' => $userData['EMAIL'],
                'PHONE' => $userData['PERSONAL_PHONE'],
                'TYPE' => !empty($userData['UF_INN']) ? 'Юр. лицо' : 'Физ. лицо',
                'INN' => $userData['UF_INN'],
                'COMPANY_NAME' => $userData['UF_COMPANY_NAME'],
            ],
            'Y',
            '',
            ['CONTENT_TYPE' => 'text/html']
        );
    }

    private function sendUserNotification(array $userData): void
    {
        CEvent::Send(
            'REGISTER_NOTIFICATION',
            SITE_ID,
            [
                'LOGIN' => $userData['LOGIN'],
            ],
            'Y',
            '',
            ['CONTENT_TYPE' => 'text/html']
        );
    }
}
