<?php

namespace App\Services\Travel\Providers\Duffel;

use App\Services\Travel\Providers\HttpProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Duffel (https://duffel.com/docs/api): flights and stays on one account.
 * Bookings are paid from the Duffel balance — keep it topped up.
 */
abstract class DuffelProvider extends HttpProvider
{
    public function key(): string
    {
        return 'duffel';
    }

    protected function configKey(): string
    {
        return 'duffel';
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withToken((string) $this->config('token'))
            ->withHeaders([
                'Duffel-Version' => $this->config('version'),
                'Accept-Encoding' => 'gzip',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config('timeout'));
    }

    protected function configured(): bool
    {
        return filled($this->config('token'));
    }

    /** Duffel wraps every body in `data`. */
    protected function body(array $data): array
    {
        return ['json' => ['data' => $data]];
    }

    /** E.164 without spaces, as Duffel wants it. */
    protected function phone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', $phone);

        return str_starts_with($digits, '+') ? $digits : '+'.ltrim($digits, '0');
    }
}
