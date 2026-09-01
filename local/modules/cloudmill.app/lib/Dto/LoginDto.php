<?php
declare(strict_types=1);

namespace Cloudmill\App\Dto;

use Bitrix\Main\Validation\Rule\NotEmpty;
use Cloudmill\App\Validation\UserLogin;

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