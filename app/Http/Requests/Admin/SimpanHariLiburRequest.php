<?php

namespace App\Http\Requests\Admin;

use App\Models\HariLibur;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanHariLiburRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tanggal' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (HariLibur::query()->whereDate('tanggal', (string) $value)->exists()) {
                        $fail('Tanggal tersebut sudah terdaftar sebagai hari libur.');
                    }
                },
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
