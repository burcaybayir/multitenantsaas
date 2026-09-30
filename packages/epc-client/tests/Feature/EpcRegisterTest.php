<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use InstallHub\EpcClient\Data\EpcCertificate;
use InstallHub\EpcClient\Enums\EnergyRating;
use InstallHub\EpcClient\Exceptions\InvalidPostcode;
use InstallHub\EpcClient\Exceptions\MissingCredentials;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;

beforeEach(fn () => $this->http = new Factory);

it('searches by postcode with basic auth and a normalised postcode', function () {
    $this->http->fake(['epc.test/*' => $this->http->response(fixtureBody('epc-search-postcode'))]);

    $result = epcRegister($this->http)->searchByPostcode('bs1 4dj', size: 50);

    expect($result)->toHaveCount(3)
        ->and($result->certificates)->each->toBeInstanceOf(EpcCertificate::class);

    $this->http->assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === EPC_BASE.'/domestic/search?postcode=BS14DJ&size=50'
        && $request->header('Authorization')[0] === 'Basic '.base64_encode('dev@installhub.test:secret-key')
        && $request->header('Accept')[0] === 'application/json');
});

it('finds the latest certificate for a property', function () {
    $this->http->fake(['epc.test/*' => $this->http->response(fixtureBody('epc-search-postcode'))]);

    $latest = epcRegister($this->http)->searchByPostcode('BS1 4DJ')->forUprn('100120123456')->latest();

    expect($latest?->lodgementDate?->format('Y-m-d'))->toBe('2023-03-01')
        ->and($latest?->currentRating)->toBe(EnergyRating::C);
});

it('treats an empty body as no results', function () {
    // The API answers "nothing found" with 200 and no body.
    $this->http->fake(['epc.test/*' => $this->http->response('', 200)]);

    expect(epcRegister($this->http)->searchByPostcode('BS1 4DJ')->isEmpty())->toBeTrue();
});

it('exposes the search-after cursor for pagination', function () {
    $this->http->fake(['epc.test/*' => $this->http->sequence()
        ->push(fixtureBody('epc-search-postcode'), 200, ['X-Next-Search-After' => 'cursor-abc'])
        ->push('', 200),
    ]);

    $register = epcRegister($this->http);
    $first = $register->searchByPostcode('BS1 4DJ', size: 3);
    $second = $register->searchByPostcode('BS1 4DJ', size: 3, searchAfter: $first->nextSearchAfter);

    expect($first->hasMorePages())->toBeTrue()
        ->and($second->hasMorePages())->toBeFalse();

    $this->http->assertSent(fn (Request $request) => str_contains($request->url(), 'search-after=cursor-abc'));
});

it('searches by UPRN', function () {
    $this->http->fake(['epc.test/*' => $this->http->response(fixtureBody('epc-search-postcode'))]);

    epcRegister($this->http)->searchByUprn('100120123456');

    $this->http->assertSent(fn (Request $request) => $request->url() === EPC_BASE.'/domestic/search?uprn=100120123456');
});

it('fetches a single certificate, or null when it does not exist', function () {
    $row = fixtureJson('epc-search-postcode')['rows'][0];
    $this->http->fake([
        'epc.test/api/v1/domestic/certificate/1398273092352019052017431649938770' => $this->http->response(['rows' => [$row]]),
        'epc.test/api/v1/domestic/certificate/*' => $this->http->response('', 404),
    ]);

    $register = epcRegister($this->http);

    expect($register->certificate('1398273092352019052017431649938770')?->currentRating)->toBe(EnergyRating::D)
        ->and($register->certificate('000'))->toBeNull();
});

it('fetches recommendations', function () {
    $this->http->fake(['epc.test/*' => $this->http->response(fixtureBody('epc-recommendations'))]);

    $recommendations = epcRegister($this->http)->recommendations('1765432098712342023030114223355018');

    expect($recommendations)->toHaveCount(2)
        ->and($recommendations[0]->description)->toBe('Solar photovoltaic panels, 2.5 kWp')
        ->and($recommendations[0]->summary)->toBeNull()
        ->and($recommendations[1]->indicativeCost)->toBe('£7,000 - £13,000');
});

it('validates input before spending an API call', function (Closure $call, string $exception) {
    $this->http->fake();

    expect(fn () => $call(epcRegister($this->http)))->toThrow($exception);

    $this->http->assertNothingSent();
})->with([
    'invalid postcode' => [fn ($register) => $register->searchByPostcode('not a postcode'), InvalidPostcode::class],
    'page size too large' => [fn ($register) => $register->searchByPostcode('BS1 4DJ', size: 5001), InvalidArgumentException::class],
    'page size zero' => [fn ($register) => $register->searchByPostcode('BS1 4DJ', size: 0), InvalidArgumentException::class],
    'non-numeric uprn' => [fn ($register) => $register->searchByUprn('12a'), InvalidArgumentException::class],
    'lmk key path traversal' => [fn ($register) => $register->certificate('../../admin'), InvalidArgumentException::class],
]);

it('refuses to call the API without credentials', function () {
    $this->http->fake();

    expect(fn () => epcRegister($this->http, key: null)->searchByPostcode('BS1 4DJ'))
        ->toThrow(MissingCredentials::class);

    $this->http->assertNothingSent();
});

it('rejects a body without the documented rows list', function (mixed $body) {
    $this->http->fake(['epc.test/*' => $this->http->response($body)]);

    expect(fn () => epcRegister($this->http)->searchByPostcode('BS1 4DJ'))->toThrow(UnexpectedResponse::class);
})->with([
    'no rows key' => [['column-names' => []]],
    'rows is an object' => [['rows' => ['a' => 1]]],
    'row is a string' => [['rows' => ['nope']]],
    'html error page' => ['<html>oops</html>'],
]);
