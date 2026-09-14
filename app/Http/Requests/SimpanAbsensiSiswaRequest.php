<?php

namespace App\Http\Requests;

use App\Enums\StatusKehadiranSiswa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanAbsensiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date_format:Y-m-d', 'date_equals:today'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'absensis' => ['present', 'array'],
            'absensis.*.siswa_id' => ['required', 'integer', 'distinct', 'exists:siswas,id'],
            'absensis.*.status' => ['required', Rule::enum(StatusKehadiranSiswa::class)],
            'absensis.*.catatan' => ['nullable', 'string', 'max:500'],
            'absensis.*.jam_datang' => ['nullable', 'date_format:H:i'],
        ];
    }
}
