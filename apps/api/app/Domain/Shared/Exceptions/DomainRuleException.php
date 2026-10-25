<?php

namespace App\Domain\Shared\Exceptions;

/**
 * A business rule rejected the request.
 *
 * Extends \DomainException so existing domain-level catches keep working, while
 * adding a stable machine code and HTTP status so the API answers with a typed
 * error instead of a 500.
 */
class DomainRuleException extends \DomainException
{
    public function __construct(
        public readonly string $codeName,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self($code, $message, 409);
    }

    public static function invalid(string $code, string $message): self
    {
        return new self($code, $message, 422);
    }

    public static function forbidden(string $code, string $message): self
    {
        return new self($code, $message, 403);
    }
}