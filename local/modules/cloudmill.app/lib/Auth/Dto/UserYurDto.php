<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Dto;

use Bitrix\Main\Validation\Rule\Phone;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\RegExp;
use CloudMill\App\Auth\Validator\Inn;

final class UserYurDto
{
    public function __construct(
        #[RegExp('/^[А-ЯЁ][а-яё]*$/u', errorMessage: "Имя должно начинаться с заглавной буквы и содержать только кириллицу")]
        #[NotEmpty(errorMessage: "Имя не может быть пустым")]
        public readonly string $name,

        #[Phone(errorMessage: "Некорректный номер телефона")]
        #[NotEmpty(errorMessage: "Номер телефона не может быть пустым")]
        public readonly string $phone,
        #[Email(errorMessage: "Некорректный E-mail")]
        #[NotEmpty(errorMessage: "E-mail не может быть пустым")]
        public readonly string $email,
        #[NotEmpty(errorMessage: "ИНН не может быть пустым")]
        #[Inn(errorMessage: "Некорректный ИНН")]
        public readonly string $INN,
        #[NotEmpty(errorMessage: "Название организации не может быть пустым")]
        public readonly string $companyName,
        #[NotEmpty(errorMessage: "Пароль не может быть пустым")]
        public readonly string $password,
    )
    {
    }
}