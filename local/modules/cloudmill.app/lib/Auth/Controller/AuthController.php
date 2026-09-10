<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use CloudMill\App\Infrastructure\Bitrix\DI\ServiceProvider;
use Bitrix\Main\Error;
use CloudMill\App\Auth\Service\AuthenticationService;
use CloudMill\App\Auth\Service\RegistrationService;
use CloudMill\App\Auth\Service\PasswordRecoveryService;
use CloudMill\App\Auth\Dto\PasswordDto;
use CloudMill\App\Auth\Dto\LoginDto;
use CloudMill\App\Auth\Dto\UserPhizDto;
use CloudMill\App\Auth\Dto\UserYurDto;

final class AuthController extends Controller
{
    protected function getDefaultPreFilters(): array
    {
        return [
            new ActionFilter\HttpMethod(['POST']),
            new ActionFilter\Csrf(),
        ];
    }

    public function authenticateAction(AuthenticationService $authenticationService, $inputs): ?array
    {
        if (!isset($inputs['login']) || !isset($inputs['password'])) {
            $this->addError(new Error('Не указаны логин или пароль'));
            return null;
        }

        $validator = ServiceProvider::ValidationService();

        $loginDto = new LoginDto($inputs['login']);
        $validationResult = $validator->validate($loginDto);
        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        $passwordDto = new PasswordDto($inputs['password']);
        $validationResult = $validator->validate($passwordDto);
        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        $loginResult = $authenticationService->login($inputs['login'], $inputs['password']);
        if (!$loginResult->isSuccess()) {
            foreach ($loginResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return $loginResult->getData();
    }

    public function registerAction(RegistrationService $registrationService, $inputs): ?array
    {
        $isYur = isset($inputs['INN']) && isset($inputs['companyName']);
        $userType = $isYur ? 'yur' : 'phiz';

        $validator = ServiceProvider::ValidationService();

        if ($isYur) {
            $dto = new UserYurDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
                INN: $inputs['INN'],
                companyName: $inputs['companyName'],
                password: $inputs['password'] ?? ''
            );
        } else {
            $dto = new UserPhizDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
                password: $inputs['password'] ?? ''
            );
        }

        $validationResult = $validator->validate($dto);
        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        $data = [
            'name' => $dto->name,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'password' => $dto->password,
        ];
        if ($isYur) {
            $data['inn'] = $dto->INN;
            $data['companyName'] = $dto->companyName;
        }

        $result = $registrationService->startRegistration($data, $userType);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true, 'message' => 'Код подтверждения отправлен на email'];
    }

    public function confirmAction(RegistrationService $registrationService, array $inputs): ?array
    {
        $email = $inputs['email'] ?? '';
        $code = $inputs['otp'] ?? '';

        $result = $registrationService->confirmRegistration($email, $code);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        $user_id = $result->getData()['user_id'];

        global $USER;

        $USER->authorize($user_id);

        return ['success' => true, 'user_id' => $user_id];
    }

    public function resendAction(RegistrationService $registrationService, string $email): ?array
    {
        $result = $registrationService->resendCode($email);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true, 'message' => 'Код отправлен по SMS'];
    }

    public function resendEmailAction(RegistrationService $registrationService, string $email): ?array
    {
        $result = $registrationService->resendCodeToEmail($email);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }
        return ['success' => true, 'message' => 'Код повторно отправлен на email'];
    }

    public function requestPasswordResetAction(PasswordRecoveryService $recoveryService, $inputs): ?array
    {
        if (!isset($inputs['login']) || empty($inputs['login'])) {
            $this->addError(new Error('Укажите email или телефон'));
            return null;
        }

        $result = $recoveryService->requestRecovery($inputs['login']);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true];
    }

    public function resetPasswordAction(PasswordRecoveryService $recoveryService, $inputs): ?array
    {
        if (!isset($inputs['token']) || empty($inputs['token'])) {
            $this->addError(new Error('Токен не передан'));
            return null;
        }
        if (!isset($inputs['password']) || empty($inputs['password'])) {
            $this->addError(new Error('Новый пароль не указан'));
            return null;
        }

        $validationResult = ServiceProvider::ValidationService()->validate(
            new PasswordDto($inputs['password'])
        );
        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        $result = $recoveryService->resetPassword($inputs['token'], $inputs['password']);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true];
    }
}
