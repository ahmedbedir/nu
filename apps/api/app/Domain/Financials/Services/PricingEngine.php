<?php

namespace App\Domain\Financials\Services;

use App\Domain\Geo\Contracts\GeoDistance;
use App\Models\Booking;
use App\Models\BookingQuote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic pre-confirmation pricing. All calculations use decimal strings
 * and the quote snapshots its inputs. It never mutates an accepted quote.
 */
class PricingEngine
{
    public function __construct(private readonly GeoDistance $geo) {}

    public function quote(Booking $booking, ?Carbon $scheduledFor = null): BookingQuote
    {
        $booking->loadMissing(['request.service', 'visitAddress']);
        $service = $booking->request->service;
        $when = $scheduledFor ?? $booking->scheduled_for ?? now();

        $lines = [];
        $callout = (string) config('homecare.billing.callout_fee', '50.00');
        $this->line($lines, 'callout', 'رسوم زيارة', 'Visit fee', '1', $callout, true);

        if ($service->billing_mode === 'time') {
            $minutes = max((int) $service->default_duration_minutes, (int) config('homecare.billing.time_minimum_minutes', 30));
            $rate = (string) config('homecare.billing.time_minute_rate', '4.00');
            $amount = bcmul((string) $minutes, $rate, 2);
            $this->line($lines, 'time', 'رعاية بالوقت', 'Time-based care', (string) $minutes, $rate, true, $amount);
        } else {
            $amount = (string) $service->base_price;
            $this->line($lines, 'procedure', $service->name_ar, $service->name_en, '1', $amount, true);
        }

        $distanceKm = $this->travelKm($booking);
        if ($distanceKm !== null && $distanceKm > 0) {
            $travelRate = (string) config('homecare.billing.travel_per_km', '8.00');
            $travelAmount = bcmul(number_format($distanceKm, 2, '.', ''), $travelRate, 2);
            $this->line($lines, 'travel', 'رسوم انتقال', 'Travel fee', number_format($distanceKm, 2, '.', ''), $travelRate, false, $travelAmount);
        }

        $subtotal = $this->sum($lines);
        $uplift = $this->nightUplift($when, $subtotal);
        if (bccomp($uplift, '0.00', 2) > 0) {
            $percent = (string) config('homecare.billing.night_uplift_percent', '25');
            $this->line($lines, 'night_uplift', 'زيادة ليلية', 'Night uplift', '1', $uplift, true, $uplift, ['percent' => $percent]);
        }

        $subtotal = $this->sum($lines);
        $commissionPercent = (string) config('homecare.billing.commission_percent', '15');
        $commission = bcmul($this->commissionableTotal($lines), bcdiv($commissionPercent, '100', 6), 2);

        return DB::transaction(function () use ($booking, $lines, $subtotal, $commission, $when) {
            $version = ((int) $booking->quotes()->max('version')) + 1;

            return BookingQuote::create([
                'booking_id' => $booking->id,
                'version' => $version,
                'lines' => $lines,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency' => config('homecare.billing.currency', 'EGP'),
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) config('homecare.billing.quote_ttl_minutes', 10)),
            ]);
        });
    }

    /** @return float|null */
    private function travelKm(Booking $booking): ?float
    {
        $address = $booking->visitAddress;
        $presence = $booking->professional?->presence;

        if (! $address?->hasCoordinates() || ! $presence?->latitude || ! $presence?->longitude) {
            return null;
        }

        return $this->geo->km(
            (float) $address->latitude,
            (float) $address->longitude,
            (float) $presence->latitude,
            (float) $presence->longitude,
        );
    }

    private function nightUplift(Carbon $when, string $base): string
    {
        $time = $when->copy()->setTimezone(config('app.timezone', 'UTC'))->format('H:i');
        $start = (string) config('homecare.billing.night_starts_at', '21:00');
        $end = (string) config('homecare.billing.night_ends_at', '07:00');
        $isNight = $time >= $start || $time < $end;

        if (! $isNight) {
            return '0.00';
        }

        $percent = bcdiv((string) config('homecare.billing.night_uplift_percent', '25'), '100', 6);

        return bcmul($base, $percent, 2);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function sum(array $lines): string
    {
        return array_reduce($lines, fn (string $sum, array $line) => bcadd($sum, (string) $line['amount'], 2), '0.00');
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function commissionableTotal(array $lines): string
    {
        return array_reduce($lines, fn (string $sum, array $line) => $line['commissionable']
            ? bcadd($sum, (string) $line['amount'], 2)
            : $sum, '0.00');
    }

    /** @param array<int, array<string, mixed>> &$lines */
    private function line(array &$lines, string $kind, string $ar, string $en, string $quantity, string $unit, bool $commissionable, ?string $amount = null, array $meta = []): void
    {
        $amount ??= bcmul($quantity, $unit, 2);
        $lines[] = [
            'kind' => $kind,
            'description_ar' => $ar,
            'description_en' => $en,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'amount' => $amount,
            'commissionable' => $commissionable,
            'meta' => $meta,
        ];
    }
}