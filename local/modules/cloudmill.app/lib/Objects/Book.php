<?php
declare(strict_types=1);

namespace CloudMill\App\Objects;

final class Book
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $picture = "",
        public readonly ?array $userFields = [],
    ) {}
}