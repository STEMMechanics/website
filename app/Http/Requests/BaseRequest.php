<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return boolean
     */
    public function authorize()
    {
        if (request()->isMethod('post') === true && method_exists($this, 'postAuthorize') === true) {
            return $this->postAuthorize();
        } elseif ((request()->isMethod('put') === true || request()->isMethod('patch') === true) && method_exists($this, 'putAuthorize') === true) {
            return $this->putAuthorize();
        } elseif (request()->isMethod('delete') === true && method_exists($this, 'destroyAuthorize') === true) {
            return $this->deleteAuthorize();
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [];

        if (method_exists($this, 'baseRules') === true) {
            $rules = $this->baseRules();
        }

        if (method_exists($this, 'postRules') === true && request()->isMethod('post') === true) {
            $rules = $this->mergeRules($rules, $this->postRules());
        } elseif (method_exists($this, 'putRules') === true && (request()->isMethod('put') === true || request()->isMethod('patch') === true)) {
            $rules = $this->mergeRules($rules, $this->postRules());
        } elseif (method_exists($this, 'destroyRules') === true && request()->isMethod('delete') === true) {
            $rules = $this->mergeRules($rules, $this->destroyRules());
        }

        return $rules;
    }

    /**
     * Merge two collections of rules.
     *
     * @param array $collection1 The first collection of rules.
     * @param array $collection2 The second collection of rules to merge.
     * @return array
     */
    private function mergeRules(array $collection1, array $collection2)
    {
        $rules = [];

        foreach ($collection1 as $key => $ruleset) {
            if (array_key_exists($key, $collection2) === true) {
                if (is_string($collection1[$key]) === true && is_string($collection2[$key]) === true) {
                    $rules[$key] = $collection1[$key] . '|' . $collection2[$key];
                } else {
                    $key_ruleset = [];

                    if (is_array($collection1[$key]) === true) {
                        $key_ruleset = $collection1[$key];
                    } elseif (is_string($collection1[$key]) === true) {
                        $key_ruleset = explode('|', $collection1[$key]);
                    }

                    if (is_array($collection2[$key]) === true) {
                        $key_ruleset = array_merge($key_ruleset, $collection2[$key]);
                    } elseif (is_string($collection1[$key]) === true) {
                        $key_ruleset = array_merge($key_ruleset, explode('|', $collection1[$key]));
                    }

                    if (count($key_ruleset) > 0) {
                        $rules[$key] = $key_ruleset;
                    }
                }//end if
            } else {
                $rules[$key] = $ruleset;
            }//end if
        }//end foreach

        foreach ($collection2 as $key => $ruleset) {
            if (array_key_exists($key, $rules) === false) {
                $rules[$key] = $collection2[$key];
            }
        }

        return $rules;
    }
}
