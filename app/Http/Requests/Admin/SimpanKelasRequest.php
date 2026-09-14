<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Kelas;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanKelasRequest extends FormRequest
{
    /**
     * Nama kelas hanya perlu unik di dalam satu kantor dan satu tahun ajaran.
     * tahun_ajaran_id tidak diambil dari request: nilainya milik server.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kelas = $this->route('kelas');
        $kelasId = $kelas instanceof Kelas ? $kelas->getKey() : null;
        $tahunAjaranId = app(TahunAjaranTerpilih::class)->id();

        return [
            'kantor_id' => ['required', 'integer', 'exists:kantors,id'],
            'nama' => [
                'required', 'string', 'max:30',
                Rule::unique('kelas', 'nama')
                    ->where(fn ($query) => $query
                        ->where('kantor_id', $this->integer('kantor_id'))
                        ->where('tahun_ajaran_id', $tahunAjaranId))
                    ->ignore($kelasId),
            ],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'wali_kelas_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->whereIn('role', [Role::Guru->value, Role::Admin->value]),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.unique' => 'Kelas dengan nama ini sudah ada di unit dan tahun ajaran yang sama.',
            'wali_kelas_id.exists' => 'Wali kelas harus guru atau admin yang terdaftar.',
        ];
    }
}
