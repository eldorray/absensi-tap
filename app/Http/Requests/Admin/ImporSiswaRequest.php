<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImporSiswaRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'kantor_id' => ['required', 'integer', 'exists:kantors,id'],
            'berkas' => ['required', 'file', 'max:1024', 'mimes:csv,txt'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kantor_id.required' => 'Pilih unit sekolah tujuan impor lebih dulu.',
            'berkas.mimes' => 'Berkas harus CSV. Di Excel pilih "Save As" lalu format CSV.',
            'berkas.max' => 'Berkas maksimal 1 MB.',
        ];
    }
}
