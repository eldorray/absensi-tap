<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanPengumumanRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:120'],
            'isi' => ['required', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
