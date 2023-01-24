<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'username' => 'required|string|max:255|min:4|unique:users',
            'first_name' => 'required|string|max:255|min:2',
            'last_name' => 'required|string|max:255|min:2',
            'email' => 'required|string|email|max:255',
            'phone' => ['string', 'regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'email_verified_at' => 'date'
        ];
    }
}
