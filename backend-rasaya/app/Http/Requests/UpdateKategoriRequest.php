<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKategoriRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }
    public function rules(): array
    {
        // sesuaikan dengan nama parameter di routes/web.php (/{kategori})
        $id = $this->route('kategori')?->id ?? $this->route('kategori_masalah')?->id;

        return [
            'kode' => ['required', 'max:10', 'alpha_num', Rule::unique('kategori_masalahs', 'kode')->ignore($id)],
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
