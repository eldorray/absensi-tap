<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanJadwalRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'jadwals' => ['required', 'array', 'min:1', 'max:7'],
            'jadwals.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'jadwals.*.jam_masuk' => ['required', 'date_format:H:i'],
            'jadwals.*.jam_pulang' => ['required', 'date_format:H:i', 'after:jadwals.*.jam_masuk'],
            'jadwals.*.toleransi_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'jadwals.*.is_hari_kerja' => ['required', 'boolean'],
        ];
    }
}
