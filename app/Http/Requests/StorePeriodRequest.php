<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // login dijamin middleware; kepemilikan = user saat ini
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
        ];
    }
}
