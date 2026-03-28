<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassHelpRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:screen,camera'],
            'target_user_id' => ['nullable', 'string', 'max:255'],
            'target_participant_identity' => ['nullable', 'string', 'max:255'],
            'target_username' => ['nullable', 'string', 'max:255'],
            'target_display_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
