<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Support\ColorPalette;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tag'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:30'],
            'color' => ['sometimes', 'required', 'string', Rule::in(ColorPalette::AUXILIARY)],
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
                if (! $this->filled('name') || $validator->errors()->has('name')) {
                    return;
                }

                $normalized = Tag::normalize($this->input('name'));

                $exists = $this->user()->tags()
                    ->where('normalized_name', $normalized)
                    ->whereKeyNot($this->route('tag'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'You already have a tag with this name.');
                }
            },
        ];
    }
}
