<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

abstract class BaseGateway implements PaymentGatewayInterface
{
    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config('payments.gateways.'.$this->code().'.'.$key, $default);
    }

    protected function http()
    {
        return Http::timeout(30)->retry(2, 100)->acceptJson();
    }
}
