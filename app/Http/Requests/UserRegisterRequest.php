<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'username' => 'required|string|min:4|max:255|unique:users',
            'password' => 'required|string|min:8',
        ];
    }
}
