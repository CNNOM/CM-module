<?php
declare(strict_types=1);

namespace CloudMill\App\Order\Dto;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Required
{
    public function __construct(public readonly string $message)
    {
    }
}
