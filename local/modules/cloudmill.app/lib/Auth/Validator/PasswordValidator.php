<?php
declare(strict_types = 1);

namespace CloudMill\App\Auth\Validator;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\ValidationService;
use CloudMill\App\Auth\Dto\PasswordDto;
use Bitrix\Main\Error;

class PasswordValidator
{
    public function validate(string $password): Result
    {
        $result = new Result();

        $validator = ServiceLocator::getInstance()->get('main.validation.service');

        $pwdObject = new PasswordDto($password);
        $validation = $validator->validate($pwdObject);

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