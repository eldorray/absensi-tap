<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SimpanGuruRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'nip' => ['nullable', 'string', 'max:30'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', Password::default()]];
    }
}
