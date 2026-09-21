<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'min_age' => ['nullable', 'integer', 'min:17', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:17', 'max:100', 'gte:min_age'],
            'gender_preference' => ['nullable', 'string'],
            'max_distance_km' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'religion' => ['nullable', 'string', 'max:80'],
            'religion_importance' => ['nullable', 'string'],
            'education' => ['nullable', 'string', 'max:160'],
            'education_importance' => ['nullable', 'string'],
            'marital_status' => ['nullable', 'string'],
            'marital_importance' => ['nullable', 'string'],
            'smoking_preference' => ['nullable', 'string', 'max:60'],
            'drinking_preference' => ['nullable', 'string', 'max:60'],
            'relationship_goal' => ['nullable', 'string'],
            'relationship_importance' => ['nullable', 'string'],
            'min_height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
            'max_height_cm' => ['nullable', 'integer', 'min:100', 'max:250', 'gte:min_height_cm'],
            'verified_only' => ['nullable', 'boolean'],
            'photo_only' => ['nullable', 'boolean'],
        ];
    }
}
