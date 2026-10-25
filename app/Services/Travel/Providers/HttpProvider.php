<?php

namespace App\Services\Travel\Providers;

use App\Services\Travel\Exceptions\ProviderException;
use App\Services\Travel\Support\ConcurrentHttp;
use App\Services\Travel\Support\Pricing;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What every HTTP provider shares: configured client, errors as
 * ProviderException, and async requests on the shared curl handler.
 */
abstract class HttpProvider
{
    public function __construct(protected Pricing $pricing, protected ConcurrentHttp $concurrent) {}

    /** `travel.providers.{configKey}` */
    abstract protected function configKey(): string;

    abstract public function key(): string;

    /** Base URL, auth headers and timeout. */
    abstract protected function http(): PendingRequest;

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("travel.providers.{$this->configKey()}.{$key}", $default);
    }

    protected function send(string $method, string $uri, array $options = []): Response
    {
        try {
            $response = $this->http()->send($method, $uri, $options);
        } catch (Throwable $e) {
            throw $this->unreachable($e);
        }

        return $this->ensureOk($response);
    }

    /** Resolves to the Response; rejects with ProviderException. */
    protected function sendAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        $promise = $this->http()->setHandler($this->concurrent->handler())->async()->send($method, $uri, $options);
        if ($promise instanceof LazyPromise) {
            $promise = $promise->buildPromise();
        }

        return $promise->then(function ($response) {
            if ($response instanceof Throwable) {
                throw $this->unreachable($response);
            }

            return $this->ensureOk($response);
        });
    }

    protected function ensureOk(Response $response): Response
    {
        if ($response->failed()) {
            Log::warning("{$this->key()} request failed", ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw ProviderException::fromResponse($this->key(), $response);
        }

        return $response;
    }

    private function unreachable(Throwable $e): ProviderException
    {
        if ($e instanceof ProviderException) {
            return $e;
        }
        Log::warning("{$this->key()} unreachable", ['error' => $e->getMessage()]);

        return new ProviderException($this->key(), "{$this->key()}: {$e->getMessage()}");
    }
}
