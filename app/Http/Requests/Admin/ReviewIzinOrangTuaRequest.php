<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Enums\StatusIzin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIzinOrangTuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Admin && $this->user()->is_active;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StatusIzin::class)->only([
                StatusIzin::Disetujui,
                StatusIzin::Ditolak,
            ])],
            'catatan_review' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
