<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // kepemilikan RHK diperiksa di controller via policy
    }

    public function rules(): array
    {
        $isFile = $this->input('evident_type') === 'file_upload';

        return [
            'evident_type' => ['required', 'in:file_upload,external_link'],
            'title' => ['required', 'string', 'max:255'],
            'file' => [$isFile ? 'required' : 'prohibited', 'file',
                'max:'.(config('evident.max_upload_mb') * 1024),
                'extensions:'.implode(',', config('evident.allowed_extensions')),
                'mimes:'.implode(',', config('evident.allowed_extensions'))],
            // Hanya http/https: menolak javascript:, data:, dsb.
            'external_url' => [$isFile ? 'prohibited' : 'required', 'url:http,https', 'max:2000'],
            'pin' => ['nullable', 'digits_between:4,8'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'berkas', 'external_url' => 'tautan', 'title' => 'judul', 'pin' => 'PIN'];
    }
}
