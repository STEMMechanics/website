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
    public function postRules()
    {
        return [
            'email' => 'required|email',
            'captcha_token' => [new Recaptcha()],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function destroyRules()
    {
        return [
            'email' => 'required|email',
            'captcha_token' => [new Recaptcha()],
        ];
    }
}
