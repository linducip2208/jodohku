<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['nullable', 'string', Rule::exists('membership_plans', 'code')],
            'subscription_plan' => ['nullable', 'string', Rule::exists('membership_plans', 'code')],
            'credit_product' => ['nullable', 'string', Rule::exists('credit_products', 'code')],
            'gateway' => ['required', 'string', 'in:ipaymu,xendit,midtrans,tripay'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'return_url' => ['nullable', 'url', 'max:512'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->input('plan_code') && ! $this->input('subscription_plan') && ! $this->input('credit_product')) {
                $v->errors()->add('plan_code', 'Select a subscription plan or credit product.');
            }
        });
    }
}
