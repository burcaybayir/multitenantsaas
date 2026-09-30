<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Support;

use DateTimeImmutable;

/**
 * Tolerant readers for API rows.
 *
 * The EPC dataset is decades of assessor-entered data: every value arrives as
 * a string, and "missing" is spelled "", "NO DATA!", "INVALID!" or "N/A".
 * Each reader returns a properly typed value or null, never a sentinel string.
 *
 * @internal
 */
final class Field
{
    private const MISSING = ['', 'NO DATA!', 'INVALID!', 'N/A', 'NODATA!'];

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function string(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return in_array(strtoupper($value), self::MISSING, true) ? null : $value;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function int(array $row, string $key): ?int
    {
        $value = self::string($row, $key);

        return $value !== null && is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function float(array $row, string $key): ?float
    {
        $value = self::string($row, $key);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function bool(array $row, string $key): ?bool
    {
        return match (strtoupper((string) self::string($row, $key))) {
            'Y', 'YES', 'TRUE', '1' => true,
            'N', 'NO', 'FALSE', '0' => false,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function date(array $row, string $key): ?DateTimeImmutable
    {
        $value = self::string($row, $key);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date === false ? null : $date;
    }
}
