<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomClientErrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:255'],
            'stack' => ['nullable', 'string', 'max:10000'],
            'context' => ['nullable', 'array'],
            'context.*' => ['nullable'],
        ];
    }
}
