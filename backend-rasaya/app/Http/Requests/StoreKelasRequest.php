<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKelasRequest extends FormRequest
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
        return [
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajarans,id'],
            'tingkat' => ['required', 'in:X,XI,XII'],
            'penjurusan' => ['nullable', 'in:IPA,IPS'],
            'rombel' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('kelass', 'rombel')->where(
                    fn($q) =>
                    $q->where('tahun_ajaran_id', $this->tahun_ajaran_id)
                        ->where('tingkat', $this->tingkat)
                        ->where('penjurusan', $this->penjurusan ?? '')
                ),
            ],
        ];
    }
}
