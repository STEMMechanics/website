<?php

namespace App\Http\Requests;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;

class UserResendVerifyEmailRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => 'required|exists:users,email',
            // 'captcha_token' => [new Recaptcha()],
        ];
    }
}
