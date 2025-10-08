<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKelasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('kelas');
        return [
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'nama' => [
                'required',
                'string',
                'max:100',
                'unique:kelas,nama,' . $id . ',id,tahun_ajaran_id,' . $this->input('tahun_ajaran_id')
            ],
            'tingkat' => ['required', 'in:X,XI,XII'],
            'wali_guru_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
