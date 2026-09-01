<?php
declare(strict_types = 1);

namespace Cloudmill\App\Controller;


use CloudMill\App\Services\AuthenticationService;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Error;
use Cloudmill\App\Dto\PasswordDto;
use Cloudmill\App\Dto\UserPhizDto;
use Cloudmill\App\Dto\UserYurDto;


class EditProfile extends Controller
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

        $validator = ServiceLocator::getInstance()->get('main.validation.service');

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

        $validator = ServiceLocator::getInstance()->get('main.validation.service');

        if ($isYur) {
            $dto = new UserYurDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
                INN: $inputs['inn'] ?? '',
                companyName: $inputs['companyName'],
                password: $inputs['email'] ?? ''
            );
        } else {
            $dto = new UserPhizDto(
                name: $inputs['name'] ?? '',
                phone: $inputs['tel'] ?? '',
                email: $inputs['email'] ?? '',
                password: $inputs['email'] ?? ''
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
            $data['inn'] = $dto->INN;
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