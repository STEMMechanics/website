<?php

namespace App\Http\Requests;

class MediaRequest extends BaseRequest
{
    public function postRules(): array
    {
        return [
            'id' => 'required_with:chunk|string',
            'chunk' => 'required_with:chunk_count|integer|min:1|max:99',
            'chunk_count' => 'required_with:chunk|integer|min:1',
        ];
    }
}
