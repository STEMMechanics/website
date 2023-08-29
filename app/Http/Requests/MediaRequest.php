<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MediaRequest extends BaseRequest
{
    public function postRules(): array
    {
        return [
            'id' => [
                Rule::requiredIf(function () {
                    return request()->has('chunk') && request('chunk') != 1;
                }),
                'string',
            ],
            'chunk' => 'required_with:chunk_count|integer|min:1|max:99',
            'chunk_count' => 'required_with:chunk|integer|min:1',
        ];
    }
}
