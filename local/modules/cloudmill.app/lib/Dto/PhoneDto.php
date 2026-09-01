<?php
declare(strict_types=1);

namespace Cloudmill\App\Dto;

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