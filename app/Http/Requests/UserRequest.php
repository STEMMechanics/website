<?php

namespace App\Http\Requests;

use App\Rules\RequiredIfAny;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use App\Rules\Uniqueish;
use Illuminate\Support\Arr;

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

        $requiredIfFieldsPresent = function (array $fields) use ($ruleUser): RequiredIf {
            return new RequiredIf(function () use ($fields, $ruleUser) {
                $input = $this->all();
                $values = Arr::only($input, $fields);

                foreach ($values as $key => $value) {
                    if ($value !== null && $value !== '') {
                        return true;
                    }
                }

                $fields = array_diff($fields, array_keys($values));

                foreach ($fields as $field) {
                    if ($ruleUser->$field !== '') {
                        return true;
                    }
                }

                return false;
            });
        };

        return [
            'first_name' => [
                'sometimes',
                $isAdminUser === true ? $requiredIfFieldsPresent(['last_name', 'display_name', 'phone']) : 'required',
                'string',
                'between:2,255',
            ],
            'last_name' => [
                'sometimes',
                $isAdminUser === true ? $requiredIfFieldsPresent(['first_name', 'last_name', 'phone']) : 'required',
                'string',
                'between:2,255',
            ],
            'display_name' => [
                'sometimes',
                $isAdminUser === true ? $requiredIfFieldsPresent(['first_name', 'display_name', 'phone']) : 'required',
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
