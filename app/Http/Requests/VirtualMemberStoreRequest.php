<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VirtualMemberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date', 'before:-17 years'],
            'city' => ['nullable', 'string', 'max:120'],
            'account_type' => ['nullable', 'string', 'in:virtual,ai'],
            'ai_personality_id' => ['nullable', 'integer', 'exists:ai_personalities,id'],
            'mode' => ['nullable', 'string', 'in:template,hybrid,ai,operator'],
            'persona_prompt' => ['nullable', 'string', 'max:4000'],
            'greeting_message' => ['nullable', 'string', 'max:2000'],
            'reply_templates' => ['nullable', 'array', 'max:50'],
            'reply_templates.*' => ['string', 'max:1000'],
        ];
    }
}
