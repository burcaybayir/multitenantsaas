<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Data;

use InstallHub\EpcClient\Exceptions\UnexpectedResponse;
use InstallHub\EpcClient\Support\Field;
use InstallHub\EpcClient\Support\Postcode;

final readonly class PostcodeLocation
{
    public function __construct(
        public Postcode $postcode,
        public float $latitude,
        public float $longitude,
        public ?string $country,
        public ?string $region,
        public ?string $adminDistrict,
    ) {}

    /**
     * @param  array<string, mixed>  $result  the "result" object from postcodes.io
     */
    public static function fromApi(array $result): self
    {
        $postcode = Postcode::tryFromString((string) Field::string($result, 'postcode'));
        $latitude = Field::float($result, 'latitude');
        $longitude = Field::float($result, 'longitude');

        // Some valid postcodes (e.g. new builds) have no coordinates yet. For
        // geocoding purposes that's as good as not found, but it's a data
        // problem, not a transport one, so it's reported as malformed.
        if ($postcode === null || $latitude === null || $longitude === null) {
            throw UnexpectedResponse::malformed('postcodes.io', 'result without postcode or coordinates');
        }

        return new self(
            postcode: $postcode,
            latitude: $latitude,
            longitude: $longitude,
            country: Field::string($result, 'country'),
            region: Field::string($result, 'region'),
            adminDistrict: Field::string($result, 'admin_district'),
        );
    }
}
