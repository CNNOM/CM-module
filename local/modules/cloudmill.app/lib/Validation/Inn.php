<?php
declare(strict_types = 1);

namespace Cloudmill\App\Validation;

use Attribute;
use Bitrix\Main\Validation\Rule\PropertyValidationAttributeInterface;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\ValidationError;
#[Attribute(Attribute::TARGET_PROPERTY)]
class Inn implements PropertyValidationAttributeInterface
{
    public function __construct(
        protected ?string $errorMessage = null
    )
    {
    }

    public function validateProperty(mixed $propertyValue): ValidationResult
    {
        $result = new ValidationResult();

        if ($propertyValue === null || $propertyValue === '') {
            return $result;
        }

        if (!$this->isValidInn($propertyValue)) {
            $message = $this->errorMessage ?: 'Некорректный ИНН (должен содержать 10 или 12 цифр с верной контрольной суммой)';
            $result->addError(new ValidationError($message));
        }

        return $result;
    }

    private function isValidInn(string $inn): bool
    {
        $inn = (string) $inn;

        if (!in_array(strlen($inn), [10, 12])) {
            return false;
        }

        if (!preg_match('/^\d+$/', $inn)) {
            return false;
        }

        if (strlen($inn) == 10) {
            $coef = [2, 4, 10, 3, 5, 9, 4, 6, 8];
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += $inn[$i] * $coef[$i];
            }
            $control = $sum % 11;
            if ($control == 10) $control = 0;
            return ($control == $inn[9]);
        }

        if (strlen($inn) == 12) {
            $coef1 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8];
            $sum1 = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum1 += $inn[$i] * $coef1[$i];
            }
            $control1 = $sum1 % 11;
            if ($control1 == 10) $control1 = 0;

            $coef2 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8];
            $sum2 = 0;
            for ($i = 0; $i < 11; $i++) {
                $sum2 += $inn[$i] * $coef2[$i];
            }
            $control2 = $sum2 % 11;
            if ($control2 == 10) $control2 = 0;

            return ($control1 == $inn[10] && $control2 == $inn[11]);
        }

        return false;
    }
}