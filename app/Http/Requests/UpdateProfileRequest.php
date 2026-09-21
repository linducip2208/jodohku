<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:-17 years'],
            'gender' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'occupation' => ['nullable', 'string', 'max:160'],
            'education' => ['nullable', 'string', 'max:160'],
            'religion' => ['nullable', 'string', 'max:80'],
            'ethnicity' => ['nullable', 'string', 'max:80'],
            'height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
            'weight_kg' => ['nullable', 'integer', 'min:25', 'max:300'],
            'body_type' => ['nullable', 'string', 'max:60'],
            'smoking' => ['nullable', 'string', 'max:60'],
            'drinking' => ['nullable', 'string', 'max:60'],
            'marital_status' => ['nullable', 'string'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'want_children' => ['nullable', 'string', 'max:60'],
            'relationship_goal' => ['nullable', 'string'],
            'languages' => ['nullable', 'string', 'max:255'],
            'zodiac' => ['nullable', 'string', 'max:30'],
            'interests' => ['nullable', 'array', 'max:20'],
            'interests.*' => ['integer', Rule::exists('interests', 'id')],
            'photos_visibility' => ['nullable', 'string'],
            'videos_visibility' => ['nullable', 'string'],
            'bio_visibility' => ['nullable', 'string'],
            'location_visibility' => ['nullable', 'string'],
            'online_visibility' => ['nullable', 'string'],
            'age_visibility' => ['nullable', 'string'],
            'show_distance' => ['nullable', 'boolean'],
            'show_online_status' => ['nullable', 'boolean'],
            'allow_profile_views' => ['nullable', 'boolean'],
        ];
    }
}
