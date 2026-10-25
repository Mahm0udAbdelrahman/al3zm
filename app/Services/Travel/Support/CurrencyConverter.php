<?php

namespace App\Services\Travel\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Converts between currencies through the display currency
 * (`travel.currency`). Rates come from `travel.rates_url` when set, cached,
 * and fall back to the static `travel.rates` table.
 */
class CurrencyConverter
{
    public function base(): string
    {
        return strtoupper(config('travel.currency'));
    }

    public function convert(float $amount, string $from, ?string $to = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to ?? $this->base());

        if ($from === $to) {
            return Currency::round($amount, $to);
        }

        $inBase = $amount * $this->rate($from);

        return Currency::round($inBase / $this->rate($to), $to);
    }

    /** Units of the display currency per one unit of [$currency]. */
    public function rate(string $currency): float
    {
        $currency = strtoupper($currency);
        if ($currency === $this->base()) {
            return 1.0;
        }

        $rate = $this->rates()[$currency] ?? null;
        if (! $rate) {
            throw new RuntimeException("No exchange rate for {$currency}.");
        }

        return (float) $rate;
    }

    /** @return array<string, float> */
    public function rates(): array
    {
        $static = array_change_key_case(config('travel.rates', []), CASE_UPPER);
        $url = config('travel.rates_url');
        if (! $url) {
            return $static;
        }

        return Cache::remember('travel:rates:'.$this->base(), config('travel.rates_ttl'), function () use ($url, $static) {
            try {
                // The feed quotes how much of each currency one base unit buys.
                $live = Http::timeout(10)->get($url)->throw()->json('rates', []);
                $rates = [];
                foreach ($live as $code => $perBase) {
                    if ($perBase > 0) {
                        $rates[strtoupper($code)] = 1 / $perBase;
                    }
                }

                return $rates + $static;
            } catch (Throwable $e) {
                Log::warning('Exchange rates unavailable, using static table', ['error' => $e->getMessage()]);

                return $static;
            }
        });
    }
}
