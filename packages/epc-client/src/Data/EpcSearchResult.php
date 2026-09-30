<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, EpcCertificate>
 */
final readonly class EpcSearchResult implements Countable, IteratorAggregate
{
    /**
     * @param  list<EpcCertificate>  $certificates
     * @param  string|null  $nextSearchAfter  cursor for the next page, null on the last page
     */
    public function __construct(
        public array $certificates,
        public ?string $nextSearchAfter = null,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->certificates === [];
    }

    public function hasMorePages(): bool
    {
        return $this->nextSearchAfter !== null;
    }

    /**
     * Most recently lodged certificate: a property can have several EPCs over
     * the years and only the latest reflects its current state.
     */
    public function latest(): ?EpcCertificate
    {
        $latest = null;

        foreach ($this->certificates as $certificate) {
            if ($latest === null || $certificate->lodgementDate > $latest->lodgementDate) {
                $latest = $certificate;
            }
        }

        return $latest;
    }

    public function forUprn(string $uprn): self
    {
        return new self(array_values(array_filter(
            $this->certificates,
            static fn (EpcCertificate $certificate): bool => $certificate->uprn === $uprn,
        )));
    }

    public function count(): int
    {
        return count($this->certificates);
    }

    /**
     * @return Traversable<int, EpcCertificate>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->certificates);
    }
}
