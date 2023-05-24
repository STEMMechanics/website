<?php

namespace App\Http\Requests;

use App\Rules\Recaptcha;

class SubscriptionRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function postRules(): array
    {
        return [
            'email' => 'required|email|unique:subscriptions',
            // 'captcha_token' => [new Recaptcha()],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function destroyRules(): array
    {
        return [
            'email' => 'required|email',
            // 'captcha_token' => [new Recaptcha()],
        ];
    }

    /**
     * Get the custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address has already subscribed',
        ];
    }
}
