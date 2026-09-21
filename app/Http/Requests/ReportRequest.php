<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'reported_user_id' => ['required_without:reportable_id', 'nullable', 'integer', Rule::exists('users', 'id')],
            'reportable_type' => ['nullable', 'string', 'max:190'],
            'reportable_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
