<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Enums;

/**
 * EPC energy efficiency band, A (best) to G (worst).
 */
enum EnergyRating: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case G = 'G';

    /**
     * Band from a SAP energy efficiency score (1-100+), per the published
     * SAP band boundaries.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 92 => self::A,
            $score >= 81 => self::B,
            $score >= 69 => self::C,
            $score >= 55 => self::D,
            $score >= 39 => self::E,
            $score >= 21 => self::F,
            default => self::G,
        };
    }

    /**
     * Lenient parsing for API values ("d", " D ", "", "INVALID!").
     */
    public static function tryFromApi(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtoupper(trim($value))) : null;
    }

    /** A = 7 ... G = 1, handy for comparisons and scoring. */
    public function rank(): int
    {
        return match ($this) {
            self::A => 7,
            self::B => 6,
            self::C => 5,
            self::D => 4,
            self::E => 3,
            self::F => 2,
            self::G => 1,
        };
    }

    public function isBetterThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
