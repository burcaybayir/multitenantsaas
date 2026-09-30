<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Data;

use DateTimeImmutable;
use InstallHub\EpcClient\Enums\EnergyRating;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;
use InstallHub\EpcClient\Support\Field;
use InstallHub\EpcClient\Support\Postcode;

/**
 * One domestic Energy Performance Certificate (a subset of the ~90 columns,
 * chosen for installer suitability assessments). The full row is kept in
 * $attributes so callers never need to change this package to read a field.
 */
final readonly class EpcCertificate
{
    /** An EPC is valid for ten years from lodgement. */
    private const VALIDITY = 'P10Y';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $lmkKey,
        public ?string $uprn,
        public string $address,
        public ?Postcode $postcode,
        public ?EnergyRating $currentRating,
        public ?EnergyRating $potentialRating,
        public ?int $currentEfficiency,
        public ?int $potentialEfficiency,
        public ?string $propertyType,
        public ?string $builtForm,
        public ?string $constructionAgeBand,
        public ?float $totalFloorArea,
        public ?string $mainFuel,
        public ?string $mainHeatingDescription,
        public ?string $roofDescription,
        public ?string $wallsDescription,
        public ?float $photoSupplyPercent,
        public ?bool $solarWaterHeating,
        public ?bool $mainsGasAvailable,
        public ?DateTimeImmutable $inspectionDate,
        public ?DateTimeImmutable $lodgementDate,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromApi(array $row): self
    {
        $lmkKey = Field::string($row, 'lmk-key') ?? throw UnexpectedResponse::malformed('EPC API', 'certificate row without lmk-key');

        $address = implode(', ', array_filter([
            Field::string($row, 'address1'),
            Field::string($row, 'address2'),
            Field::string($row, 'address3'),
        ])) ?: (Field::string($row, 'address') ?? '');

        return new self(
            lmkKey: $lmkKey,
            uprn: Field::string($row, 'uprn'),
            address: $address,
            postcode: Postcode::tryFromString((string) Field::string($row, 'postcode')),
            currentRating: EnergyRating::tryFromApi($row['current-energy-rating'] ?? null),
            potentialRating: EnergyRating::tryFromApi($row['potential-energy-rating'] ?? null),
            currentEfficiency: Field::int($row, 'current-energy-efficiency'),
            potentialEfficiency: Field::int($row, 'potential-energy-efficiency'),
            propertyType: Field::string($row, 'property-type'),
            builtForm: Field::string($row, 'built-form'),
            constructionAgeBand: Field::string($row, 'construction-age-band'),
            totalFloorArea: Field::float($row, 'total-floor-area'),
            mainFuel: Field::string($row, 'main-fuel'),
            mainHeatingDescription: Field::string($row, 'mainheat-description'),
            roofDescription: Field::string($row, 'roof-description'),
            wallsDescription: Field::string($row, 'walls-description'),
            photoSupplyPercent: Field::float($row, 'photo-supply'),
            solarWaterHeating: Field::bool($row, 'solar-water-heating-flag'),
            mainsGasAvailable: Field::bool($row, 'mains-gas-flag'),
            inspectionDate: Field::date($row, 'inspection-date'),
            lodgementDate: Field::date($row, 'lodgement-date'),
            attributes: $row,
        );
    }

    public function expiresOn(): ?DateTimeImmutable
    {
        return $this->lodgementDate?->add(new \DateInterval(self::VALIDITY));
    }

    public function isValidOn(DateTimeImmutable $date): bool
    {
        $expiry = $this->expiresOn();

        return $expiry !== null && $date < $expiry;
    }
}
