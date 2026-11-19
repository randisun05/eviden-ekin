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
        $ext = implode(',', config('evident.allowed_extensions'));

        return [
            'evident_type' => ['required', 'in:file_upload,external_link'],
            'title' => ['required', 'string', 'max:255'],
            'files' => [$isFile ? 'required' : 'prohibited', 'array', 'min:1', 'max:'.config('evident.max_files')],
            'files.*' => ['file',
                'max:'.(config('evident.max_upload_mb') * 1024),
                'extensions:'.$ext,
                'mimes:'.$ext],
            // Hanya http/https: menolak javascript:, data:, dsb.
            'external_url' => [$isFile ? 'prohibited' : 'required', 'url:http,https', 'max:2000'],
            'pin' => ['nullable', 'digits_between:4,8'],
        ];
    }

    /** Total ukuran semua berkas dibatasi agar request & staging tetap wajar. */
    public function after(): array
    {
        return [function ($validator) {
            $files = $this->file('files');
            if (! is_array($files) || $validator->errors()->has('files.*')) {
                return;
            }
            $total = array_sum(array_map(fn ($f) => $f->getSize() ?: 0, $files));
            if ($total > config('evident.max_total_mb') * 1024 * 1024) {
                $validator->errors()->add('files', 'Total ukuran berkas melebihi '.config('evident.max_total_mb').' MB.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['files' => 'berkas', 'files.*' => 'berkas', 'external_url' => 'tautan', 'title' => 'judul', 'pin' => 'PIN'];
    }
}
