<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInputSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'siswa';
    } // peran dicek di controller
    public function rules(): array
    {
        return [
            // siswa_id opsional (kalau siswa login, ambil dari auth di controller)
            'siswa_id' => ['nullable', 'exists:siswas,id'],
            'tanggal' => ['nullable', 'date'],
            'teks' => ['required', 'string', 'min:5', 'max:2000'],
            'avg_emosi' => ['nullable', 'numeric', 'between:0,10'], // 0..10
            'kategori_ids' => ['nullable', 'array'],
            'kategori_ids.*' => ['integer', 'exists:kategori_masalahs,id'],
            'meta' => ['nullable', 'array'],
        ];
    }

}
