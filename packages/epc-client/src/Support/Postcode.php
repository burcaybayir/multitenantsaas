<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Support;

use InstallHub\EpcClient\Exceptions\InvalidPostcode;
use Stringable;

/**
 * A normalised UK postcode ("sw1a1aa", " SW1A 1AA " → "SW1A 1AA").
 *
 * Validating here, before any HTTP call, means we never spend an API request
 * (or a rate-limit token) on input that can't possibly match, and the value
 * is always safe to put in a URL path.
 */
final readonly class Postcode implements Stringable
{
    // Outward code (area + district) and inward code (sector + unit).
    private const PATTERN = '/^([A-Z]{1,2}[0-9][A-Z0-9]?)([0-9][A-Z]{2})$/';

    private function __construct(
        public string $outward,
        public string $inward,
    ) {}

    public static function fromString(string $value): self
    {
        return self::tryFromString($value) ?? throw InvalidPostcode::for($value);
    }

    public static function tryFromString(string $value): ?self
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (preg_match(self::PATTERN, $compact, $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2]);
    }

    public static function from(self|string $value): self
    {
        return $value instanceof self ? $value : self::fromString($value);
    }

    /** "SW1A 1AA" */
    public function formatted(): string
    {
        return "{$this->outward} {$this->inward}";
    }

    /** "SW1A1AA" */
    public function compact(): string
    {
        return $this->outward.$this->inward;
    }

    public function equals(self $other): bool
    {
        return $this->compact() === $other->compact();
    }

    public function __toString(): string
    {
        return $this->formatted();
    }
}
