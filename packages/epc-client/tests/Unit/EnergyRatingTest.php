<?php

declare(strict_types=1);

use InstallHub\EpcClient\Enums\EnergyRating;

it('maps SAP scores to bands at the boundaries', function (int $score, EnergyRating $rating) {
    expect(EnergyRating::fromScore($score))->toBe($rating);
})->with([
    [100, EnergyRating::A], [92, EnergyRating::A],
    [91, EnergyRating::B], [81, EnergyRating::B],
    [80, EnergyRating::C], [69, EnergyRating::C],
    [68, EnergyRating::D], [55, EnergyRating::D],
    [54, EnergyRating::E], [39, EnergyRating::E],
    [38, EnergyRating::F], [21, EnergyRating::F],
    [20, EnergyRating::G], [1, EnergyRating::G],
]);

it('parses API values leniently', function (mixed $value, ?EnergyRating $expected) {
    expect(EnergyRating::tryFromApi($value))->toBe($expected);
})->with([
    ['D', EnergyRating::D],
    [' c ', EnergyRating::C],
    ['', null],
    ['INVALID!', null],
    [null, null],
    [4, null],
]);

it('orders bands from best to worst', function () {
    expect(EnergyRating::A->isBetterThan(EnergyRating::B))->toBeTrue()
        ->and(EnergyRating::G->isBetterThan(EnergyRating::F))->toBeFalse()
        ->and(EnergyRating::C->isAtLeast(EnergyRating::C))->toBeTrue()
        ->and(EnergyRating::D->isAtLeast(EnergyRating::C))->toBeFalse();
});
