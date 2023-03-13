<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $user = $this->route('user');

        return [
            'username' => [
                'string',
                'max:255',
                'min:4',
                Rule::unique('users')->ignore($user->id)->when(
                    $this->username !== $user->username,
                    function ($query) {
                        return $query->where('username', $this->username);
                    }
                ),
            ],
            'first_name' => 'string|max:255|min:2',
            'last_name' => 'string|max:255|min:2',
            'email' => 'string|email|max:255',
            'phone' => ['nullable','regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'password' => 'string|min:8'
        ];
    }
}
