<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'in:text,image,video,audio,file,gift,system,sticker,poll'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'metadata' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.file_path' => ['required_with:attachments', 'string', 'max:512'],
            'attachments.*.file_name' => ['nullable', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:120'],
            'attachments.*.file_size' => ['nullable', 'integer', 'min:0', 'max:52428800'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (trim((string) $this->input('body')) === '' && empty($this->input('attachments'))) {
                $v->errors()->add('body', 'Message body or attachment required.');
            }
        });
    }
}
