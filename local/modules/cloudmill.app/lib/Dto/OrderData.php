<?php

declare(strict_types=1);

namespace Cloudmill\App\Dto;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Required
{
    public function __construct(public readonly string $message)
    {
    }
}

final class OrderData
{
    public function __construct(
        public readonly string $customerType,
        #[Required('Укажите имя')]
        public readonly string $personName = '',
        #[Required('Укажите телефон')]
        public readonly string $personPhone = '',
        public readonly string $personEmail = '',
        #[Required('Укажите контактное лицо')]
        public readonly string $companyContactName = '',
        #[Required('Укажите телефон')]
        public readonly string $companyPhone = '',
        public readonly string $companyEmail = '',
        #[Required('Укажите название организации')]
        public readonly string $companyName = '',
        public readonly string $companyInn = '',
        public readonly string $address = '',
        public readonly string $shippingDate = '',
        public readonly string $comment = '',
        public readonly int $deliveryId = 0,
    ) {
    }

    public function isLegal(): bool
    {
        return $this->customerType === 'legal';
    }

    public function email(): string
    {
        return trim($this->isLegal() ? $this->companyEmail : $this->personEmail);
    }

    public function customerName(): string
    {
        return trim($this->isLegal() ? $this->companyContactName : $this->personName);
    }

    public function phone(): string
    {
        return trim($this->isLegal() ? $this->companyPhone : $this->personPhone);
    }
}
