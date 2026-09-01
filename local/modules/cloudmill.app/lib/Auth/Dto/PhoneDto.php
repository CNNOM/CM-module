<?php
declare(strict_types=1);

namespace CloudMill\App\Auth\Dto;

use Bitrix\Main\Validation\Rule\Phone;
use Bitrix\Main\Validation\Rule\NotEmpty;

final class PhoneDto
{
    public function __construct(
        #[NotEmpty]
        #[Phone]
        public ?string $phone
    )
    {
    }
}