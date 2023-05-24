<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ShortlinkRequest extends BaseRequest
{
    /**
     * Apply the additional POST base rules to this request
     *
     * @return array<string, mixed>
     */
    public function postRules(): array
    {
        return [
            'code' => 'required|string|max:255|min:2|unique:shortlinks',
            'url' => 'required|string|max:255|min:2',
        ];
    }

    /**
     * Get the validation rules that apply to PUT request.
     *
     * @return array<string, mixed>
     */
    public function putRules(): array
    {
        $shortlink = $this->route('shortlink');

        return [
            'code' => ['required', 'string', 'max:255', 'min:2', Rule::unique('shortlinks')->ignore($shortlink->id)],
            'url' => 'required|string|max:255|min:2',
        ];
    }
}
