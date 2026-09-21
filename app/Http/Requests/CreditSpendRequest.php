<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditSpendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:255'],
            'feature' => ['nullable', 'string', 'in:superlike,boost,rewind,gift,spotlight,read_receipt'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
