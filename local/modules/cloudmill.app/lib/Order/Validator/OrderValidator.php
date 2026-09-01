<?php

declare(strict_types=1);

namespace CloudMill\App\Order\Validator;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\ValidationError;
use CloudMill\App\Auth\Dto\EmailDto;
use CloudMill\App\Order\Dto\OrderDataDto;
use RuntimeException;

final class OrderValidator
{
    public function validate(array $inputs): OrderDataDto
    {
        $customerType = (string)($inputs['customerType'] ?? 'individual');
        if (!in_array($customerType, ['individual', 'legal'], true)) {
            throw new RuntimeException('Некорректный тип покупателя');
        }

        $data = new OrderDataDto(
            customerType: $customerType,
            personName: $this->value($inputs, 'person-name'),
            personPhone: $this->value($inputs, 'person-tel'),
            personEmail: $this->value($inputs, 'person-email'),
            companyContactName: $this->value($inputs, 'company-contact-name'),
            companyPhone: $this->value($inputs, 'company-tel'),
            companyEmail: $this->value($inputs, 'company-email'),
            companyName: $this->value($inputs, 'company-organization'),
            companyInn: $this->value($inputs, 'company-inn'),
            address: $this->value($inputs, 'address'),
            shippingDate: $this->value($inputs, 'shipping-date'),
            comment: $this->value($inputs, 'comment'),
            deliveryId: (int)($inputs['delivery'] ?? 0),
        );

        $errors = new ValidationResult();
        $this->validateRequiredFields($data, $errors);
        $this->validateOptionalContacts($data, $errors);

        if (!$errors->isSuccess()) {
            throw new RuntimeException(implode('; ', array_map(
                static fn($error): string => $error->getMessage(),
                $errors->getErrors()
            )));
        }

        return $data;
    }

    private function validateRequiredFields(OrderDataDto $data, ValidationResult $result): void
    {
        $fields = $data->isLegal()
            ? [
                'companyContactName' => $data->companyContactName,
                'companyPhone' => $data->companyPhone,
                'companyName' => $data->companyName,
            ]
            : [
                'personName' => $data->personName,
                'personPhone' => $data->personPhone,
            ];

        foreach ($fields as $field => $value) {
            if (trim($value) !== '') {
                continue;
            }

            $property = new \ReflectionProperty(OrderDataDto::class, $field);
            $attributes = $property->getAttributes(\CloudMill\App\Auth\Dto\Required::class);
            if ($attributes) {
                $result->addError(new ValidationError($attributes[0]->newInstance()->message));
            }
        }
    }

    private function validateOptionalContacts(OrderDataDto $data, ValidationResult $result): void
    {
        $validator = ServiceLocator::getInstance()->get('main.validation.service');

        if ($data->email() !== '') {
            $validation = $validator->validate(new EmailDto($data->email()));
            foreach ($validation->getErrors() as $error) {
                $result->addError(new ValidationError($error->getMessage()));
            }
        }

        $phone = preg_replace('/\D+/', '', $data->phone());
        if ($data->phone() !== '' && ($phone === null || !preg_match('/^(?:7|8)\d{10}$/', $phone))) {
            $result->addError(new ValidationError('Некорректный номер телефона'));
        }
    }

    private function value(array $inputs, string $key): string
    {
        return trim((string)($inputs[$key] ?? ''));
    }
}
