<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }
    public function rules(): array
    {
        return [
            'kode' => ['required', 'max:10', 'alpha_num', 'unique:kategori_masalahs,kode'],
            'nama' => ['required', 'max:100'],
            'deskripsi' => ['nullable', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}