<?php

namespace App\Payments;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $drivers = [];

    public function driver(?string $code = null): PaymentGatewayInterface
    {
        $code ??= (string) config('payments.default', 'midtrans');
        if (isset($this->drivers[$code])) {
            return $this->drivers[$code];
        }
        $cfg = config('payments.gateways.'.$code);
        if (! $cfg || empty($cfg['driver'])) {
            throw new \RuntimeException("Payment gateway [{$code}] not configured.");
        }
        $class = $cfg['driver'];
        $instance = app($class);
        if (! $instance instanceof PaymentGatewayInterface) {
            throw new \RuntimeException("Gateway driver must implement PaymentGatewayInterface.");
        }
        $this->drivers[$code] = $instance;

        return $instance;
    }

    /** Add a new gateway at runtime without touching business logic. */
    public function extend(string $code, PaymentGatewayInterface $driver): void
    {
        $this->drivers[$code] = $driver;
    }

    /** @return string[] */
    public function available(): array
    {
        return collect(config('payments.gateways', []))
            ->filter(fn ($g) => ! empty($g['enabled']))
            ->sortBy('priority')->keys()->all();
    }
}
