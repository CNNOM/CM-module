<?php
declare(strict_types=1);

namespace Cloudmill\App\Dto;

final class RutubeVideoDto
{
    public function __construct(
        public ?string $id = '',
        public ?string $name = '',
        public ?string $rawLink = '',
        public ?string $link = '',
        public ?string $picJpg = '',
        public null|string|int $duration = '',
        public ?string $host = 'Rutube'
    )
    {
    }
}
