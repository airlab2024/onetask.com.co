<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class StrictValidator extends Validator
{
    public function validateEmail($attribute, $value, $parameters)
    {
        if (!is_string($value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return false;
        }

        return parent::validateEmail($attribute, $value, $parameters);
    }
}
