<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * ISO-8601 datetime with an explicit, required timezone designator
     * (Z or ±HH:MM), optionally with fractional seconds. Rejects
     * "2026-12-15T18:00:00" (no timezone) to eliminate ambiguity about
     * which timezone the wall-clock digits belong to.
     */
    public const DUE_AT_FORMAT = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->route('project')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_at' => ['nullable', 'date', 'regex:'.self::DUE_AT_FORMAT],
        ];
    }
}
