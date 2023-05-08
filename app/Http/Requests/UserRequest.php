<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\Uniqueish;

class UserRequest extends BaseRequest
{
    /**
     * Fields that are required unless all are null.
     *
     * @var string[]
     */
    protected $required_with_all = ['first_name','last_name','display_name','phone'];


    /**
     * Apply the additional POST base rules to this request
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        return [
            'first_name' => 'required|string|max:255|min:2',
            'last_name' => 'required|string|max:255|min:2',
            'display_name' => 'required|string|max:255|uniqueish:users',
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
        $user = $this->route('user');

        $required_with_all = count($this->required_with_all) > 0 ? 'required_with_all:' . implode(',', $this->required_with_all) : '';

        return [
            'first_name' => "nullable|string|required_if_any:users,last_name,display_name,phone,password|between:2,255",
            'last_name' => "nullable|required_if_any:users,first_name,display_name,phone,password|string|max:255|min:2",
            'display_name' => [
                'nullable',
                'required_if_any:users,first_name,last_name,phone,password',
                'string',
                'max:255',
                'min:2',
                (new Uniqueish('users', 'display_name'))->ignore($user->id),
            ],
            'email' => [
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)->when(
                    $this->email !== $user->email,
                    function ($query) {
                        return $query->where('email', $this->email);
                    }
                ),
            ],
            'phone' => ['nullable', 'regex:/^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/'],
            'password' => "nullable|{$required_with_all}|string|min:8"
        ];
    }
}
