<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // kepemilikan diperiksa di controller via policy
    }

    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'digits_between:4,8'],
            'remove_pin' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return ['pin' => 'PIN', 'expires_at' => 'tanggal kedaluwarsa'];
    }
}
