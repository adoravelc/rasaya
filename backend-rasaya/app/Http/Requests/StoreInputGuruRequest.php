<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInputGuruRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'guru';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guru_id' => ['nullable', 'integer', 'exists:gurus,user_id'],
            'siswa_kelas_id' => ['required', 'integer', 'exists:siswa_kelass,id'],
            'tanggal' => ['nullable', 'date'],
            'teks' => ['required', 'string', 'min:5', 'max:5000'],
            'gambar' => ['nullable', 'string', 'max:255'],
            'kondisi_siswa' => ['required', Rule::in(['green', 'yellow', 'orange', 'red', 'black', 'grey'])],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => ['integer', 'exists:kategori_masalahs,id'],
        ];
    }
}
