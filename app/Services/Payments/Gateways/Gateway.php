<?php

namespace App\Services\Payments\Gateways;

use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Client\Response;
use RuntimeException;

abstract class Gateway implements PaymentGateway
{
    protected function config(string $key, mixed $default = null): mixed
    {
        return config("payments.gateways.{$this->key()}.{$key}", $default);
    }

    public function currencies(): array
    {
        return array_map('strtoupper', $this->config('currencies', []));
    }

    protected function ensureOk(Response $response): Response
    {
        if ($response->failed()) {
            $message = $response->json('errors.0.description')
                ?? $response->json('error.message')
                ?? $response->json('description')
                ?? $response->json('message')
                ?? "HTTP {$response->status()}";

            throw new RuntimeException("{$this->key()}: {$message}");
        }

        return $response;
    }
}
