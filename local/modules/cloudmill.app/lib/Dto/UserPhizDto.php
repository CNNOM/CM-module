<?php
declare(strict_types=1);

namespace Cloudmill\App\Dto;

use Bitrix\Main\Validation\Rule\Phone;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\RegExp;

final class UserPhizDto
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
        #[NotEmpty(errorMessage: "Пароль не может быть пустым")]
        public readonly string $password,
    )
    {
    }
}