<?php
declare(strict_types=1);

namespace Cloudmill\App\Dto;

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