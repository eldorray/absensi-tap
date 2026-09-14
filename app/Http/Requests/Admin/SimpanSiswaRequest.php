<?php

namespace App\Http\Requests\Admin;

use App\Enums\JenisKelamin;
use App\Models\Siswa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanSiswaRequest extends FormRequest
{
    /**
     * NIS diuji unik hanya di dalam kantornya: MI dan SMP menomori siswanya
     * sendiri-sendiri. Pemeriksaan di sini supaya admin melihat pesan di
     * bawah kolomnya, bukan halaman galat dari unique index.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siswa = $this->route('siswa');
        $siswaId = $siswa instanceof Siswa ? $siswa->getKey() : null;

        return [
            'kelas_id' => ['nullable', 'integer'],
            'kantor_id' => ['required', 'integer', 'exists:kantors,id'],
            'nis' => [
                'required', 'string', 'max:30',
                Rule::unique('siswas', 'nis')
                    ->where(fn ($query) => $query->where('kantor_id', $this->integer('kantor_id')))
                    ->ignore($siswaId),
            ],
            'nisn' => ['nullable', 'string', 'max:20', Rule::unique('siswas', 'nisn')->ignore($siswaId)],
            'nama' => ['required', 'string', 'max:120'],
            'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.unique' => 'NIS ini sudah dipakai siswa lain di unit yang sama.',
            'nisn.unique' => 'NISN ini sudah terdaftar.',
        ];
    }
}
