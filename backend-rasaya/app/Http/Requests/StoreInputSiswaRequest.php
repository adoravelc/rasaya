<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // kalau memang hanya siswa yang boleh submit, biarkan seperti ini.
        return $this->user()?->role === 'siswa';
    }

    public function rules(): array
    {
        return [
            // NOTE: relasi siswa keyed ke user_id
            'siswa_id' => ['nullable', 'integer', 'exists:siswas,user_id'],

            // <-- tambahkan rule untuk siswa_dilapor_id
            'siswa_dilapor_id' => ['nullable', 'integer', 'different:siswa_id', 'exists:siswas,user_id'],

            'tanggal' => ['nullable', 'date'],
            'teks' => ['required', 'string', 'min:5', 'max:2000'],
            'avg_emosi' => ['nullable', 'numeric', 'between:0,10'],

            // optional bukti/gambar mock
            'gambar' => ['nullable', 'string', 'max:255'],

            // 0 = draft, 1 = submit
            'status_upload' => ['required', 'in:0,1'],

            'kategori_ids' => ['nullable', 'array'],
            'kategori_ids.*' => ['integer', 'exists:kategori_masalahs,id'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
