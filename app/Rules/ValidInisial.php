<?php

namespace App\Rules;

use App\Services\StafApiService;
use Illuminate\Contracts\Validation\Rule;

class ValidInisial implements Rule
{
    private $stafApiService;
    private $errorMessage;

    public function __construct()
    {
        $this->stafApiService = new StafApiService();
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        $result = $this->stafApiService->validateInisial($value);

        if (!$result['valid']) {
            $this->errorMessage = $result['message'];
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return $this->errorMessage ?: 'Inisial tidak valid';
    }
}
