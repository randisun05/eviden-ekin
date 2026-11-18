<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRhkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // kepemilikan diperiksa di controller via policy
    }

    public function rules(): array
    {
        $period = $this->route('period') ?? $this->route('rhk')?->period;

        return [
            // Kode dipakai sebagai nama folder Drive: larang "/" dan "\".
            'code' => ['required', 'string', 'max:50', 'regex:/^[^\/\\\\]+$/',
                Rule::unique('rhks', 'code')->where('period_id', $period?->id)->ignore($this->route('rhk')?->id)],
            'title' => ['required', 'string', 'max:2000'],
            'target_description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode tidak boleh mengandung karakter "/" atau "\\".',
            'code.unique' => 'Kode RHK sudah dipakai pada periode ini.',
        ];
    }
}
