<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UserRequest extends BaseRequest
{
    /**
     * Apply the additional POST base rules to this request
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        return [
            'username' => 'required|string|max:255|min:4|unique:users',
            'first_name' => 'required|string|max:255|min:2',
            'last_name' => 'required|string|max:255|min:2',
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => ['string', 'regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'email_verified_at' => 'date'
        ];
    }

    /**
     * Get the validation rules that apply to PUT request.
     *
     * @return array<string, mixed>
     */
    public function putRules()
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
            'display_name' => 'string|max:255|min:2',
            'email' => 'string|email|max:255',
            'phone' => ['nullable','regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'password' => 'string|min:8'
        ];
    }
}
