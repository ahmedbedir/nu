<?php

namespace App\Domain\Notifications\Contracts;

/**
 * SMS delivery boundary.
 *
 * Local/dev uses the logging adapter. A real provider must be wired only after the
 * vendor privacy/security review required by decision #239, and its delivery
 * receipts must be recorded separately from "sent".
 */
interface SmsSender
{
    /**
     * @return array{provider: string, reference: ?string, delivered: bool}
     */
    public function send(string $phone, string $message, string $purpose): array;
}