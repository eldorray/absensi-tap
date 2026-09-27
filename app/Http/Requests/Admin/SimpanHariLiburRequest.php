<?php

namespace App\Http\Requests\Admin;

use App\Models\HariLibur;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanHariLiburRequest extends FormRequest
{
    /**
     * Libur panjang (libur semester, cuti bersama) cukup satu entri rentang
     * lewat "sampai"; tanggal yang sudah terdaftar di dalam rentang dilewati.
     */
    public const MAKS_HARI_RENTANG = 60;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tanggal' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->filled('sampai')) {
                        return;
                    }

                    if (HariLibur::query()->whereDate('tanggal', (string) $value)->exists()) {
                        $fail('Tanggal tersebut sudah terdaftar sebagai hari libur.');
                    }
                },
            ],
            'sampai' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:tanggal',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $mulai = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->input('tanggal'));
                    $selesai = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

                    if ($mulai !== false && $selesai !== false && $mulai->diff($selesai)->days >= self::MAKS_HARI_RENTANG) {
                        $fail('Rentang hari libur paling panjang '.self::MAKS_HARI_RENTANG.' hari.');
                    }
                },
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
