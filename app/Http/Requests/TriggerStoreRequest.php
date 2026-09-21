<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'event_name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'daily_cap' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'active_hours_start' => ['nullable', 'integer', 'min:0', 'max:23'],
            'active_hours_end' => ['nullable', 'integer', 'min:0', 'max:23'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array', 'max:20'],
            'actions.*.action_type' => ['required_with:actions', 'string', 'max:80'],
            'actions.*.template' => ['nullable', 'string', 'max:2000'],
            'actions.*.delay_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }
}
