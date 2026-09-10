<?php
declare(strict_types = 1);

namespace CloudMill\App\Auth\Controller;


use CloudMill\App\Auth\Service\AuthenticationService;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use CloudMill\App\Infrastructure\Bitrix\DI\ServiceProvider;
use Bitrix\Main\Error;
use CloudMill\App\Auth\Dto\PasswordDto;
use CloudMill\App\Auth\Dto\ProfilePhizDto;
use CloudMill\App\Auth\Dto\ProfileYurDto;


class ProfileController extends Controller
{
    protected function getDefaultPreFilters(): array
    {
        return [
            new ActionFilter\HttpMethod(['POST']),
            new ActionFilter\Csrf(),
            new ActionFilter\Authentication()
        ];
    }

    public function changePasswordAction(AuthenticationService $authenticationService, $inputs): ?array
    {
        if (!isset($inputs['password']) || empty($inputs['password'])) {
            $this->addError(new Error('Новый пароль не указан'));
            return null;
        }

        $validator = ServiceProvider::ValidationService();

        $passwordDto = new PasswordDto($inputs['password']);
        $validationResult = $validator->validate($passwordDto);

        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        global $USER;

        $result = $authenticationService->changePassword((int)$USER->getId(), $inputs['password']);
        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true];
    }

    public function editProfileAction(AuthenticationService $authenticationService, $inputs): ?array
    {
        $isYur = isset($inputs['inn']) && isset($inputs['companyName']);
        $userType = $isYur ? 'yur' : 'phiz';

        $validator = ServiceProvider::ValidationService();

        if ($isYur) {
            $dto = new ProfileYurDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
                inn: $inputs['inn'] ?? '',
                companyName: $inputs['companyName'] ?? '',
            );
        } else {
            $dto = new ProfilePhizDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
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
        ];

        if ($isYur) {
            $data['inn'] = $dto->inn;
            $data['companyName'] = $dto->companyName;
        }

        global $USER;

        $editResult = $authenticationService->editProfile((int)$USER->getId(), $userType, $data);

        if (!$editResult->isSuccess()) {
            foreach ($editResult->getErrors() as $error) {
                $this->addError(new Error($error->getMessage()));
            }
            return null;
        }

        return ['success' => true];
    }
}
