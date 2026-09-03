<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatusIzin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIzinRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Pending bukan hasil review, jadi tidak diterima di sini.
            'status' => ['required', Rule::enum(StatusIzin::class)->only([
                StatusIzin::Disetujui,
                StatusIzin::Ditolak,
            ])],
            'catatan_review' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
