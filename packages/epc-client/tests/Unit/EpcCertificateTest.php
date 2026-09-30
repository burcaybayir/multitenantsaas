<?php

declare(strict_types=1);

use InstallHub\EpcClient\Data\EpcCertificate;
use InstallHub\EpcClient\Enums\EnergyRating;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;

it('maps an API row to typed fields', function () {
    $certificate = EpcCertificate::fromApi(fixtureJson('epc-search-postcode')['rows'][0]);

    expect($certificate)
        ->lmkKey->toBe('1398273092352019052017431649938770')
        ->uprn->toBe('100120123456')
        ->address->toBe('12 Orchard Way')
        ->currentRating->toBe(EnergyRating::D)
        ->potentialRating->toBe(EnergyRating::B)
        ->currentEfficiency->toBe(61)
        ->totalFloorArea->toBe(86.0)
        ->mainHeatingDescription->toBe('Boiler and radiators, mains gas')
        ->roofDescription->toBe('Pitched, 100 mm loft insulation')
        ->solarWaterHeating->toBeFalse()
        ->mainsGasAvailable->toBeTrue()
        ->and($certificate->postcode?->formatted())->toBe('BS1 4DJ')
        ->and($certificate->lodgementDate?->format('Y-m-d'))->toBe('2019-05-20');
});

it('turns the dataset\'s sentinel values into null', function () {
    $rows = fixtureJson('epc-search-postcode')['rows'];

    expect(EpcCertificate::fromApi($rows[1])->photoSupplyPercent)->toBeNull()   // "NO DATA!"
        ->and(EpcCertificate::fromApi($rows[1])->solarWaterHeating)->toBeNull() // ""
        ->and(EpcCertificate::fromApi($rows[2])->photoSupplyPercent)->toBeNull(); // "INVALID!"
});

it('joins multi-line addresses', function () {
    expect(EpcCertificate::fromApi(fixtureJson('epc-search-postcode')['rows'][2])->address)
        ->toBe('Flat 3, 14 Orchard Way');
});

it('keeps the raw row for fields the DTO does not map', function () {
    $row = fixtureJson('epc-search-postcode')['rows'][0] + ['co2-emissions-current' => '3.1'];

    expect(EpcCertificate::fromApi($row)->attributes['co2-emissions-current'])->toBe('3.1');
});

it('is valid for ten years from lodgement', function () {
    $certificate = EpcCertificate::fromApi(fixtureJson('epc-search-postcode')['rows'][0]); // lodged 2019-05-20

    expect($certificate->expiresOn()?->format('Y-m-d'))->toBe('2029-05-20')
        ->and($certificate->isValidOn(new DateTimeImmutable('2029-05-19')))->toBeTrue()
        ->and($certificate->isValidOn(new DateTimeImmutable('2029-05-20')))->toBeFalse();
});

it('rejects rows without an identifier', function () {
    expect(fn () => EpcCertificate::fromApi(['address1' => 'x']))->toThrow(UnexpectedResponse::class);
});
