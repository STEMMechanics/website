<?php

namespace App\Http\Requests;

use App\Rules\RequiredIfAny;
use Illuminate\Validation\Rule;
use App\Rules\Uniqueish;

class UserRequest extends BaseRequest
{
    /**
     * Apply the additional POST base rules to this request
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        $user = auth()->user();
        $isAdminUser = $user->hasPermission('admin/users');

        return [
            'first_name' => ($isAdminUser === true ? 'required_with:last_name,display_name,phone' : 'required') . '|string|max:255|min:2',
            'last_name' => ($isAdminUser === true ? 'required_with:first_name,display_name,phone' : 'required') . '|string|max:255|min:2',
            'display_name' => [
                $isAdminUser === true ? 'required_with:first_name,last_name,phone' : 'required',
                'string',
                'max:255',
                new Uniqueish('users')
            ],
            'email' => 'required|string|email|max:255|unique:users',
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
        $user = auth()->user();
        $ruleUser = $this->route('user');
        $isAdminUser = $user->hasPermission('admin/users');

        return [
            'first_name' => [
                // $isAdminUser === true ? 'required_with:last_name,display_name,phone' : 'required',
                'string',
                'between:2,255',
            ],
            // 'last_name' => $isAdminUser === true ? 'required_with:first_name,display_name,phone|string|between:2,255' : 'required|string|between:2,255',
            'last_name' => 'string|between:2,255',
            'display_name' => [
                // $isAdminUser === true ? 'required_with:first_name,last_name,phone' : 'required',
                'string',
                'between:2,255',
                (new Uniqueish('users', 'display_name'))->ignore($ruleUser->id)
            ],
            'email' => [
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($ruleUser->id)->when(
                    $this->email !== $ruleUser->email,
                    function ($query) {
                        return $query->where('email', $this->email);
                    }
                ),
            ],
            'phone' => ['nullable', 'regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'password' => "nullable|string|min:8"
        ];
    }
}
