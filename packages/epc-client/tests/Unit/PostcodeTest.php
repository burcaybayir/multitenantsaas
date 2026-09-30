<?php

declare(strict_types=1);

use InstallHub\EpcClient\Exceptions\ClientException;
use InstallHub\EpcClient\Exceptions\InvalidPostcode;
use InstallHub\EpcClient\Support\Postcode;

it('normalises valid postcodes', function (string $input, string $formatted, string $compact) {
    $postcode = Postcode::fromString($input);

    expect($postcode->formatted())->toBe($formatted)
        ->and($postcode->compact())->toBe($compact)
        ->and((string) $postcode)->toBe($formatted);
})->with([
    'already formatted' => ['SW1A 1AA', 'SW1A 1AA', 'SW1A1AA'],
    'lower case, no space' => ['sw1a1aa', 'SW1A 1AA', 'SW1A1AA'],
    'extra whitespace' => ['  bs1   4dj ', 'BS1 4DJ', 'BS14DJ'],
    'A9 9AA' => ['M1 1AE', 'M1 1AE', 'M11AE'],
    'A99 9AA' => ['B33 8TH', 'B33 8TH', 'B338TH'],
    'AA9 9AA' => ['CR2 6XH', 'CR2 6XH', 'CR26XH'],
    'AA99 9AA' => ['DN55 1PT', 'DN55 1PT', 'DN551PT'],
    'A9A 9AA' => ['W1A 0AX', 'W1A 0AX', 'W1A0AX'],
]);

it('rejects invalid postcodes', function (string $input) {
    expect(Postcode::tryFromString($input))->toBeNull()
        ->and(fn () => Postcode::fromString($input))->toThrow(InvalidPostcode::class);
})->with([
    'empty' => '',
    'outward only' => 'SW1A',
    'too long' => 'SW1A 1AAA',
    'digits first' => '1AA 1AA',
    'path traversal' => '../admin',
    'query injection' => 'BS1 4DJ&size=5000',
]);

it('is catchable as the package base exception', function () {
    expect(fn () => Postcode::fromString('nope'))->toThrow(ClientException::class);
});

it('compares by value', function () {
    expect(Postcode::fromString('bs14dj')->equals(Postcode::fromString('BS1 4DJ')))->toBeTrue();
});
