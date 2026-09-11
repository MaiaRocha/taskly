<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncTaskTagsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['present', 'array', 'max:5'],
            'tag_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('tag_ids') || $validator->errors()->has('tag_ids.*')) {
                    return;
                }

                $tagIds = $this->input('tag_ids', []);

                $ownedCount = $this->user()->tags()->whereIn('id', $tagIds)->count();

                if ($ownedCount !== count($tagIds)) {
                    $validator->errors()->add('tag_ids', 'One or more tags are invalid.');
                }
            },
        ];
    }
}
