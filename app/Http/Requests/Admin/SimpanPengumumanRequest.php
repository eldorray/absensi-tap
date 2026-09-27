<?php

namespace App\Http\Requests\Admin;

use App\Support\IsiKaya;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SimpanPengumumanRequest extends FormRequest
{
    /**
     * Panjang maksimal isi, dihitung dari teks yang terbaca -- bukan dari HTML
     * mentah, yang membengkak oleh tag format.
     */
    public const MAKS_TEKS_ISI = 2000;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:120'],
            'isi' => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Editor mengirim "<p></p>" untuk isi kosong, jadi "required" saja tidak
     * cukup: yang dinilai teks setelah HTML-nya dibersihkan.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('isi') || ! is_string($this->input('isi'))) {
                    return;
                }

                $teks = IsiKaya::teks(IsiKaya::bersihkan($this->input('isi')));

                if ($teks === '') {
                    $validator->errors()->add('isi', 'Isi pengumuman wajib diisi.');
                } elseif (mb_strlen($teks) > self::MAKS_TEKS_ISI) {
                    $validator->errors()->add('isi', 'Isi pengumuman maksimal '.self::MAKS_TEKS_ISI.' karakter.');
                }
            },
        ];
    }
}
