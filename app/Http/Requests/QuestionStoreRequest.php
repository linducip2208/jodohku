<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->isAdmin();
    }

    public function rules(): array
    {
        return [
            'question_category_id' => ['nullable', 'integer', Rule::exists('question_categories', 'id')],
            'questionnaire_version_id' => ['nullable', 'integer', Rule::exists('questionnaire_versions', 'id')],
            'category_key' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:40'],
            'question_text' => ['required', 'string', 'max:1000'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*.option_text' => ['required_with:options', 'string', 'max:500'],
            'options.*.option_value' => ['nullable', 'string', 'max:255'],
            'options.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
