<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswerQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:200'],
            'answers.*.question_id' => ['required', 'integer', Rule::exists('questions', 'id')],
            'answers.*.question_option_id' => ['nullable', 'integer', Rule::exists('question_options', 'id')],
            'answers.*.answer_text' => ['nullable', 'string', 'max:2000'],
            'answers.*.answer_value' => ['nullable', 'string', 'max:255'],
            'answers.*.answer_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'answers.*.importance' => ['nullable', 'string'],
            'questionnaire_version_id' => ['nullable', 'integer', Rule::exists('questionnaire_versions', 'id')],
        ];
    }
}
