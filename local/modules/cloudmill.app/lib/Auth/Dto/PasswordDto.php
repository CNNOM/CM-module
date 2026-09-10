<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Dto;

use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\RegExp;

final class PasswordDto
{
    public function __construct(
        #[NotEmpty(errorMessage: 'Новый пароль не может быть пустым')]
        #[Length(min: 8, max: 32, errorMessage: 'Пароль должен быть от 8 до 32 символов')]
        public ?string $password
    )
    {
    }
}
