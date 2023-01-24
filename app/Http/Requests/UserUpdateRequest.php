<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'username' => 'string|max:255|min:6|unique:users',
            'first_name' => 'string|max:255|min:2',
            'last_name' => 'string|max:255|min:2',
            'email' => 'string|email|max:255',
            'phone' => ['nullable','regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'password' => 'string|min:8'
        ];
    }
}
