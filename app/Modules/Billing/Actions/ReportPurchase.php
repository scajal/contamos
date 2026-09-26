<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;

/**
 * Report a Paddle checkout to Google Analytics as a purchase,
 * through the Measurement Protocol, so it counts as a
 * conversion even when the browser never reports it.
 */
class ReportPurchase
{
    /**
     * The Measurement Protocol endpoint events are sent to.
     */
    public const string URL = 'https://www.google-analytics.com/mp/collect';

    /**
     * Send the purchase of the given Paddle transaction, and say
     * whether Google Analytics accepted it.
     *
     * @param  array{id: string, customer_id: string, origin?: string, currency_code: non-empty-string, billed_at: string, custom_data?: array{ga_client_id?: string, ga_session_id?: string}|null, items?: array<int, array{price: array{id: string, description?: string}, quantity?: int}>, details: array{totals: array{total: numeric-string, tax: numeric-string}}}  $transaction
     */
    public function using(array $transaction): bool
    {
        $measurementId = config()->string('services.google.analytics.measurement_id', '');
        $apiSecret = config()->string('services.google.analytics.api_secret', '');

        // Without both credentials there is nowhere to send it,
        // which is the case outside production.
        if ($measurementId === '' || $apiSecret === '') {
            return false;
        }

        // Only a checkout is a conversion. Renewals and plan
        // changes bill a customer who already converted.
        if (($transaction['origin'] ?? null) !== 'web') {
            return false;
        }

        $currency = $transaction['currency_code'];
        $totals = $transaction['details']['totals'];

        // The checkout page passes the browser's identifiers, so
        // the purchase joins the visit the ad brought in. A
        // browser that blocked analytics still counts, under
        // an identifier of its own.
        $clientId = $transaction['custom_data']['ga_client_id'] ?? 'paddle.'.$transaction['customer_id'];
        $sessionId = $transaction['custom_data']['ga_session_id'] ?? null;

        $event = [
            'name' => 'purchase',
            'params' => array_filter([
                'currency' => $currency,
                'items' => array_map(fn (array $item): array => [
                    'item_id' => $item['price']['id'],
                    'item_name' => $item['price']['description'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                ], $transaction['items'] ?? []),
                'session_id' => $sessionId,
                'tax' => $this->decimal($totals['tax'], $currency),
                'transaction_id' => $transaction['id'],
                'value' => $this->decimal((string) ((int) $totals['total'] - (int) $totals['tax']), $currency),
            ], fn (mixed $value): bool => $value !== null),
        ];

        // A failure is reported but never raised, so Paddle does
        // not retry a webhook whose own work already succeeded.
        return rescue(fn (): bool => Http::timeout(5)
            ->withQueryParameters(['api_secret' => $apiSecret, 'measurement_id' => $measurementId])
            ->post(self::URL, [
                'client_id' => $clientId,
                'events' => [$event],
                'timestamp_micros' => CarbonImmutable::parse($transaction['billed_at'])->getTimestampMs() * 1000,
            ])
            ->successful(), false);
    }

    /**
     * Turn an amount in the currency's minor units into the
     * decimal number Google Analytics expects.
     *
     * @param  numeric-string  $minorUnits
     * @param  non-empty-string  $currency
     */
    protected function decimal(string $minorUnits, string $currency): float
    {
        return (float) new DecimalMoneyFormatter(new ISOCurrencies)
            ->format(new Money($minorUnits, new Currency($currency)));
    }
}
