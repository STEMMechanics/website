<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PostRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to POST requests.
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        return [
            'slug' => 'string|min:6|unique:posts',
            'title' => 'string|min:6|max:255',
            'publish_at' => 'date',
            'user_id' => 'uuid|exists:users,id',
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
            'slug' => [
                'string',
                'min:6',
                Rule::unique('posts')->ignoreModel($this->post),
            ],
            'title' => 'string|min:6|max:255',
            'publish_at' => 'date',
            'user_id' => 'uuid|exists:users,id',
        ];
    }
}
