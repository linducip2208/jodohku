<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificationSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:identity,photo,video,phone,email,premium'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*.document_type' => ['nullable', 'string', 'max:80'],
            'documents.*.file_path' => ['required_with:documents', 'string', 'max:512'],
            'documents.*.mime_type' => ['nullable', 'string', 'max:120'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf,mp4,mov'],
        ];
    }
}
