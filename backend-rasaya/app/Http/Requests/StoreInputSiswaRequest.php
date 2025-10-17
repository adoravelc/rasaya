<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izinkan siswa, guru, atau admin untuk submit
        return in_array($this->user()?->role, ['siswa', 'guru', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            // Pakai roster (siswa_kelass) bukan langsung siswas
            'siswa_kelas_id' => ['nullable', 'integer', 'exists:siswa_kelass,id'],
            'siswa_dilapor_kelas_id' => ['nullable', 'integer', 'different:siswa_kelas_id', 'exists:siswa_kelass,id'],

            'tanggal' => ['nullable', 'date'],
            'teks' => ['required', 'string', 'min:5', 'max:2000'],
            'avg_emosi' => ['nullable', 'numeric', 'between:0,10'],

            // optional bukti/gambar asli (file upload)
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // max ~2MB

            // 0 = draft, 1 = submit
            'status_upload' => ['required', 'in:0,1'],

            'kategori_ids' => ['nullable', 'array'],
            'kategori_ids.*' => ['integer', 'exists:kategori_masalahs,id'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
