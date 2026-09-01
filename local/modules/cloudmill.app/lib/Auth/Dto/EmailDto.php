<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Dto;

use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\NotEmpty;

final class EmailDto
{
    public function __construct(
        #[NotEmpty]
        #[Email]
        public ?string $email
    )
    {
    }
}