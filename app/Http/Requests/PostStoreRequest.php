<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'slug' => 'string|min:6|unique:posts',
            'title' => 'string|min:6|max:255',
            'publish_at' => 'date',
            'user_id' => 'uuid|exists:users,id',
        ];
    }
}
