<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ArticleRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to POST requests.
     *
     * @return array<string, mixed>
     */
    public function postRules()
    {
        return [
            'slug' => 'required|string|min:6|unique:articles',
            'title' => 'required|string|min:6|max:255',
            'publish_at' => 'required|date',
            'user_id' => 'required|uuid|exists:users,id',
            'content' => 'required|string|min:6',
            'hero' => 'required|uuid|exists:media,id',
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
                Rule::unique('articles')->ignoreModel($this->article),
            ],
            'title' => 'string|min:6|max:255',
            'publish_at' => 'date',
            'user_id' => 'uuid|exists:users,id',
            'content' => 'string|min:6',
            'hero' => 'uuid|exists:media,id',
        ];
    }
}
