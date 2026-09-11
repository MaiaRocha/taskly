<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAttachmentsRequest extends FormRequest
{
    /**
     * The extensions accepted for an attachment. Validated against the
     * file's real, sniffed content (via File::types(), i.e. the "mimes"
     * rule) rather than the client-supplied extension or an explicit list
     * of MIME strings, which would be brittle for Office formats.
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp',
        'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attachment::class, $this->route('task')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'required',
                File::types(self::ALLOWED_EXTENSIONS)->max('5mb'),
            ],
        ];
    }
}
