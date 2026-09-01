<?php
declare(strict_types = 1);

namespace Cloudmill\App\Validation;

use Attribute;
use Bitrix\Main\Validation\Rule\PropertyValidationAttributeInterface;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationService;
use Bitrix\Main\DI\ServiceLocator;
use Cloudmill\App\Dto\PhoneDto;
use Cloudmill\App\Dto\EmailDto;

#[Attribute(Attribute::TARGET_PROPERTY)]
class UserLogin implements PropertyValidationAttributeInterface
{
    private ?string $type;
    private string $errorMessage;

    const PHONE_REGX = "/^(\+7|8)[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}$/";
    const EMAIL_REGX = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

    public function __construct(
        string $errorMessage = '{{error}}'
    )
    {
        $this->errorMessage = $errorMessage;
    }

    public function validateProperty(mixed $propertyValue): ValidationResult
    {
        $result = new ValidationResult();

        $this->getType($propertyValue);

        if (!$this->type) {
            $result->addError(new ValidationError(
                str_replace('{{error}}', 'Неверный логин', $this->errorMessage)
            ));
            return $result;
        }

        $validator = ServiceLocator::getInstance()->get('main.validation.service');

        switch ($this->type) {
            case 'PHONE':
                $phoneObj = new PhoneDto($propertyValue);
                $result = $validator->validate($phoneObj);
                if (!$result->isSuccess()) {
                    $errorMsg = implode(', ', $result->getErrors());
                    $result->addError(new ValidationError(
                        str_replace('{{error}}', $errorMsg, $this->errorMessage)
                    ));
                }
                break;
            case 'EMAIL':
                $emailObj = new EmailDto($propertyValue);
                $result = $validator->validate($emailObj);
                if (!$result->isSuccess()) {
                    $errorMsg = implode(', ', $result->getErrors());
                    $result->addError(new ValidationError(
                        str_replace('{{error}}', $errorMsg, $this->errorMessage)
                    ));
                }
                break;
            default:
                $result->addError(new ValidationError(
                    str_replace('{{error}}', 'Неверный логин', $this->errorMessage)
                ));
        }

        return $result;
    }

    private function getType($value): void
    {
        if (preg_match(self::PHONE_REGX, $value)) {
        } elseif (preg_match(self::EMAIL_REGX, $value)) {
            $this->type = 'EMAIL';
        } else {
            $this->type = null;
        }
    }
}