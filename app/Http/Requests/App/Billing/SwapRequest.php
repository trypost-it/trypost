<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_id' => ['required', 'string'],
        ];
    }
}
