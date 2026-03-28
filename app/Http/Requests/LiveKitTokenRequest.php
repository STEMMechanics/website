<?php

namespace App\Http\Requests;

use App\Models\ClassSession;
use Illuminate\Foundation\Http\FormRequest;

class LiveKitTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'class_session_id' => ['required', 'uuid', 'exists:class_sessions,id'],
        ];
    }

    public function classSession(): ClassSession
    {
        /** @var ClassSession $classSession */
        $classSession = ClassSession::query()->whereKey((string) $this->input('class_session_id'))->firstOrFail();

        return $classSession;
    }
}
