<?php

namespace App\Http\Requests;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Izin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AjukanIzinRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::enum(TipeIzin::class)],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * Tolak rentang yang bertabrakan dengan izin pending atau disetujui milik
     * guru yang sama. Izin yang sudah ditolak tidak menghalangi apa pun.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mulai = $this->date('tanggal_mulai');
            $selesai = $this->date('tanggal_selesai');

            if ($mulai === null || $selesai === null) {
                return;
            }

            $bertabrakan = Izin::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('status', [StatusIzin::Pending, StatusIzin::Disetujui])
                ->where('tanggal_mulai', '<=', $selesai)
                ->where('tanggal_selesai', '>=', $mulai)
                ->exists();

            if ($bertabrakan) {
                $validator->errors()->add(
                    'tanggal_mulai',
                    'Sudah ada pengajuan izin pada rentang tanggal itu.',
                );
            }
        });
    }
}
