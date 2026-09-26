<?php

namespace App\Http\Requests\OrangTua;

use App\Enums\Role;
use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\IzinOrangTua;
use App\Models\TahunAjaran;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AjukanIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || $user->role !== Role::OrangTua || ! $user->is_active) {
            return false;
        }

        $siswaId = $this->integer('siswa_id');

        if ($siswaId <= 0) {
            return true;
        }

        return $user->siswas()
            ->where('siswas.id', $siswaId)
            ->where('siswas.is_active', true)
            ->exists();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'siswa_id' => ['required', 'integer', Rule::exists('siswas', 'id')->where('is_active', true)],
            'tipe' => ['required', Rule::enum(TipeIzin::class)->only([TipeIzin::Izin, TipeIzin::Sakit])],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $siswaId = $this->integer('siswa_id');
            $mulai = $this->date('tanggal_mulai');
            $selesai = $this->date('tanggal_selesai');

            if ($siswaId <= 0 || $mulai === null || $selesai === null) {
                return;
            }

            $tahunAjaran = TahunAjaran::aktif();

            if ($tahunAjaran === null
                || $mulai->lt($tahunAjaran->tanggal_mulai)
                || $mulai->gt($tahunAjaran->tanggal_selesai)
                || $selesai->lt($tahunAjaran->tanggal_mulai)
                || $selesai->gt($tahunAjaran->tanggal_selesai)) {
                $pesan = 'Tanggal pengajuan harus berada dalam tahun ajaran aktif.';
                $validator->errors()->add('tanggal_mulai', $pesan);
                $validator->errors()->add('tanggal_selesai', $pesan);

                return;
            }

            $bertabrakan = IzinOrangTua::query()
                ->where('siswa_id', $siswaId)
                ->whereIn('status', [StatusIzin::Pending, StatusIzin::Disetujui])
                ->where('tanggal_mulai', '<=', $selesai)
                ->where('tanggal_selesai', '>=', $mulai)
                ->exists();

            if ($bertabrakan) {
                $validator->errors()->add('tanggal_mulai', 'Sudah ada pengajuan untuk anak ini pada rentang tanggal tersebut.');
            }
        });
    }
}
