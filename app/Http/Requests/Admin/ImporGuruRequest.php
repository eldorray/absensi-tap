<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImporGuruRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'berkas' => ['required', 'file', 'max:1024', 'mimes:csv,txt'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'berkas.mimes' => 'Berkas harus CSV. Di Excel pilih "Save As" lalu format CSV.',
            'berkas.max' => 'Berkas maksimal 1 MB.',
        ];
    }
}
