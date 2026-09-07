<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanPengaturanAbsensiRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'toleransi_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'buka_masuk_menit' => ['required', 'integer', 'min:0', 'max:720'],
            'tutup_masuk_menit' => ['required', 'integer', 'min:0', 'max:1440'],
            'buka_pulang_menit' => ['required', 'integer', 'min:0', 'max:720'],
        ];
    }
}
