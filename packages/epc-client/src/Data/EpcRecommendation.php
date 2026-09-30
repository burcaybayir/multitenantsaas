<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Data;

use InstallHub\EpcClient\Support\Field;

/**
 * An improvement suggested by the assessor, e.g. "Solar photovoltaic panels".
 */
final readonly class EpcRecommendation
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $lmkKey,
        public ?int $itemNumber,
        public ?string $improvementId,
        public ?string $summary,
        public ?string $description,
        public ?string $indicativeCost,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromApi(array $row): self
    {
        return new self(
            lmkKey: (string) Field::string($row, 'lmk-key'),
            itemNumber: Field::int($row, 'improvement-item'),
            improvementId: Field::string($row, 'improvement-id'),
            summary: Field::string($row, 'improvement-summary-text'),
            description: Field::string($row, 'improvement-descr-text'),
            indicativeCost: Field::string($row, 'indicative-cost'),
            attributes: $row,
        );
    }
}
