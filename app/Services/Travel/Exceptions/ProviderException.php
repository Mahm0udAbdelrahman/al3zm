<?php

namespace App\Services\Travel\Exceptions;

use Illuminate\Http\Client\Response;

/** A provider answered with an error or could not be reached. */
class ProviderException extends TravelException
{
    public function __construct(public readonly string $provider, string $message, public readonly ?array $body = null, int $status = 502)
    {
        parent::__construct($message, $status);
    }

    public static function fromResponse(string $provider, Response $response): self
    {
        $body = $response->json();
        $message = data_get($body, 'errors.0.message')
            ?? data_get($body, 'errors.0.title')
            ?? data_get($body, 'error.message')
            ?? data_get($body, 'message')
            ?? data_get($body, 'description')
            ?? "HTTP {$response->status()}";

        return new self($provider, "{$provider}: {$message}", is_array($body) ? $body : null);
    }

    public function render($request)
    {
        return $this->sendResponse(502, __('The travel provider could not complete the request.'), [
            'provider' => $this->provider,
            'error' => $this->getMessage(),
        ]);
    }
}
