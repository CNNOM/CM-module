<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Dto;

use Bitrix\Main\Validation\Rule\NotEmpty;
use CloudMill\App\Auth\Validator\UserLogin;

final class LoginDto
{
    public function __construct(
        #[NotEmpty]
        #[UserLogin]
        public ?string $login
    )
    {
    }
}