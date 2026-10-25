<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Development adapter: writes the message to the log and never claims delivery.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message, string $purpose): array
    {
        Log::info('sms.dev_outbox', [
            'phone' => $this->mask($phone),
            'purpose' => $purpose,
            'message' => $message,
        ]);

        return [
            'provider' => 'log',
            'reference' => null,
            // A log write is not delivery. Never report it as delivered.
            'delivered' => false,
        ];
    }

    private function mask(string $phone): string
    {
        $len = strlen($phone);

        return $len <= 4 ? str_repeat('*', $len) : substr($phone, 0, 3).str_repeat('*', max(0, $len - 5)).substr($phone, -2);
    }
}