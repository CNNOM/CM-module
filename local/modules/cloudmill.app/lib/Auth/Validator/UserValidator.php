<?php
declare(strict_types = 1);

namespace CloudMill\App\Auth\Validator;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\ValidationService;
use CloudMill\App\Auth\Dto\UserPhizDto;
use CloudMill\App\Auth\Dto\UserYurDto;
use Bitrix\Main\Error;

class UserValidator
{
    public function validate(array $data, string $type): Result
    {
        $result = new Result();

        $validator = ServiceLocator::getInstance()->get('main.validation.service');

        if ($type == 'yur') {
            $userObject = new UserYurDto(
                $data['name'],
                $data['tel'],
                $data['email'],
                $data['inn'],
                $data['companyName'],
                $data['password'],
            );
        } elseif ($type == 'phiz') {
            $userObject = new UserPhizDto(
                $data['name'],
                $data['tel'],
                $data['email'],
                $data['password'],
            );
        } else {
            $result->addError(new Error("Невалидные данные"));
            return $result;
        }

        $validation = $validator->validate($userObject);

        if (!$validation->isSuccess()) {
            foreach ($validation->getErrors() as $error) {
                $result->addError(new Error($error->getMessage()));
            }
        } else {
            $result->setData(['valid' => true]);
        }

        return $result;
    }
}