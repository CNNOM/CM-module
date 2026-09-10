<?php
declare(strict_types = 1);

namespace CloudMill\App\Auth\Service;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\Loader;
use CUser;

final class AuthenticationService
{
    /** @var \CUser Глобальный объект текущего пользователя */
    private $user;

    /**
     * @throws \RuntimeException Если не удалось загрузить модуль main
     */
    public function __construct()
    {
        global $USER;

        if (!Loader::includeModule('main')) {
            throw new \RuntimeException('Модуль main не загружен');
        }

        $this->user = $USER;
    }

    /**
     * Выполняет вход пользователя по логину и паролю.
     *
     * @param string $login Логин (или email) пользователя
     * @param string $password Пароль в открытом виде
     * @param bool $remember Запомнить пользователя (увеличить время жизни сессии)
     *
     * @return Result Результат операции. В случае успеха в данных может содержаться массив с полями пользователя.
     *                При ошибке в коллекцию будут добавлены объекты Error с кодами ошибок Bitrix.
     */
    public function login(string $login, string $password, string $remember = "N"): Result
    {
        $result = new Result();

        if ($this->user->IsAuthorized()) {
            $result->setData($this->getUserData());
            return $result;
        }

        $loginResult = $this->user->Login($login, $password, $remember);

        if ($loginResult === true) {
            $result->setData($this->getUserData());
        } else {
            $result->addError(new Error(
                $this->getErrorMessage($loginResult['ERROR_TYPE'] ?? '')
            ));
        }

        return $result;
    }

    /**
     * Выполняет выход пользователя из системы.
     */
    public function logout(): void
    {
        $this->user->Logout();
    }

    /**
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->user->IsAuthorized();
    }

    /**
     *
     * @return array|null Ассоциативный массив с полями: id, login, email, name, lastName.
     *                     null, если пользователь не авторизован.
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $this->getUserData();
    }

    /**
     * @return array
     */
    private function getUserData(): array
    {
        $userId = (int)$this->user->GetID();
        $fields = UserTable::getList([
            'select' => [
                'ID',
                'NAME',
                'EMAIL',
                'PERSONAL_PHONE',
                'LOGIN',
                'UF_INN',
                'UF_COMPANY_NAME',
            ],
            'filter' => [
                'ID' => $userId,
            ],
            'limit' => 1,
        ])->fetch();

        return [
            'id' => (int)$fields['ID'],
            'name' => $fields['NAME'],
            'email' => $fields['EMAIL'],
            'phone' => $fields['PERSONAL_PHONE'],
            'login' => $fields['LOGIN'],
            'inn' => $fields['UF_INN'],
            'companyName' => $fields['UF_COMPANY_NAME'],
        ];
    }

    /**
     *
     * @param string $code Код ошибки
     *
     * @return string
     */
    private function getErrorMessage(string $code): string
    {
        $messages = [
            'LOGIN' => 'Неверный логин или пароль',
            'USER_BLOCKED' => 'Пользователь заблокирован',
            'AUTH_USER_NOT_FOUND' => 'Пользователь не найден',
            'AUTH_OTP_REQUIRED' => 'Требуется одноразовый пароль',
        ];
        return $messages[$code] ?? 'Ошибка авторизации (код: ' . $code . ')';
    }

    public function changePassword(int $userId, string $newPassword): Result
    {
        $result = new Result();

        $user = new CUser();
        $updateResult = $user->Update($userId, [
            'PASSWORD' => $newPassword,
            'CONFIRM_PASSWORD' => $newPassword,
        ]);

        if (!$updateResult) {
            return $result->addError(new Error('Ошибка изменения пароля: ' . $user->LAST_ERROR));
        }

        return $result;
    }

    public function editProfile(int $userId, string $userType, array $data): Result
    {
        $result = new Result();

        $updateFields = [
            'NAME' => $data['name'],
            'EMAIL' => $data['email'],
            'PERSONAL_PHONE' => $data['phone'],
            'PHONE_NUMBER' => $data['phone'],
        ];
        if ($userType === 'yur') {
            $updateFields['UF_INN'] = $data['inn'] ?? '';
            $updateFields['UF_COMPANY_NAME'] = $data['companyName'] ?? '';
        }

        $user = new CUser();

        if (!$user->Update($userId, $updateFields)) {
            $result->addError(new Error('Ошибка обновления пользователя: ' . $user->LAST_ERROR));
        }

        return $result;
    }
}
