<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AnalyticsRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to POST requests.
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        return [
            'type' => 'required|string',
        ];
    }

    /**
     * Get the validation rules that apply to PUT request.
     *
     * @return array<string, mixed>
     */
    public function putRules()
    {
        return [
            'type' => 'string',
            'useragent' => 'string',
            'ip' => 'ipv4|ipv6',
            'session' => 'number',
        ];
    }
}
