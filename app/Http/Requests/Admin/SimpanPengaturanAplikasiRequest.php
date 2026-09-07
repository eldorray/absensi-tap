<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanPengaturanAplikasiRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:60'],
            // SVG sengaja tidak diterima: berkas SVG bisa memuat <script> dan
            // dilayani dari domain yang sama dengan aplikasi.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,webp', 'max:128'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.mimes' => 'Logo harus PNG, JPG, atau WEBP.',
            'logo.max' => 'Logo maksimal 512 KB.',
            'favicon.mimes' => 'Favicon harus PNG, ICO, atau WEBP.',
            'favicon.max' => 'Favicon maksimal 128 KB.',
        ];
    }
}
