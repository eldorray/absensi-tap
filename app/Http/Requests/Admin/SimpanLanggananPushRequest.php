<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use NotificationChannels\WebPush\PushSubscription;

class SimpanLanggananPushRequest extends FormRequest
{
    /**
     * Bentuknya mengikuti PushSubscription.toJSON() dari browser.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Server akan POST ke URL ini, jadi hanya https yang diterima.
            'endpoint' => ['required', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }
}
