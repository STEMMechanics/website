<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class EventRequest extends BaseRequest
{
    /**
     * Apply the base rules to this request
     *
     * @return array<string, mixed>
     */
    public function baseRules(): array
    {
        return [
            'title'             => 'min:6',
            'location' => [
                Rule::in(['online', 'physical']),
            ],
            'address'           => 'string|nullable',
            'start_at'          => 'date',
            'end_at'            => 'date|after:start_date',
            'publish_at'        => 'date|nullable',
            'status' => [
                Rule::in(['draft', 'soon', 'open', 'closed', 'cancelled']),
            ],
            'registration_type' => [
                Rule::in(['none', 'email', 'link', 'message']),
            ],
            'registration_data' => [
                Rule::when(strcasecmp('email', $this->attributes->get('registration_type')) == 0, 'required|email'),
                Rule::when(strcasecmp('link', $this->attributes->get('registration_type')) == 0, 'required|url'),
                Rule::when(strcasecmp('message', $this->attributes->get('registration_type')) == 0, 'required|message'),
            ],
            'hero'              => 'uuid|exists:media,id',
        ];
    }

    /**
     * Apply the additional POST base rules to this request
     *
     * @return array<string, mixed>
     */
    protected function postRules(): array
    {
        return [
            'title'             => 'required',
            'location'          => 'required',
            'address'           => 'required_if:location,physical',
            'start_at'          => 'required',
            'end_at'            => 'required',
            'status'            => 'required',
            'registration_type' => 'required',
            'hero'              => 'required',
        ];
    }
}
