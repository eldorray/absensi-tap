<?php

namespace App\Http\Requests\Admin;

use App\Models\Kelas;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TempatkanSiswaRequest extends FormRequest
{
    /**
     * Siswa hanya boleh masuk kelas di unit yang sama dengannya. Tanpa aturan
     * ini, satu salah pilih di dropdown memindahkan anak MI ke rombel SMP dan
     * absensinya ikut pindah unit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kelas = $this->route('kelas');
        $kantorId = $kelas instanceof Kelas ? $kelas->kantor_id : 0;

        return [
            'siswa_ids' => ['required', 'array', 'min:1'],
            'siswa_ids.*' => [
                'integer', 'distinct',
                Rule::exists('siswas', 'id')->where('kantor_id', $kantorId)->where('is_active', true),
            ],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'siswa_ids.required' => 'Pilih minimal satu siswa.',
            'siswa_ids.min' => 'Pilih minimal satu siswa.',
            'siswa_ids.*.exists' => 'Ada siswa yang bukan siswa aktif dari unit kelas ini.',
        ];
    }
}
