<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use InstallHub\EpcClient\Exceptions\InvalidPostcode;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;

beforeEach(fn () => $this->http = new Factory);

it('geocodes a postcode', function () {
    $this->http->fake(['postcodes.test/*' => $this->http->response(fixtureBody('postcode-found'))]);

    $location = postcodeLookup($this->http)->lookup('bs14dj');

    expect($location)
        ->latitude->toBe(51.452293)
        ->longitude->toBe(-2.597298)
        ->country->toBe('England')
        ->region->toBe('South West')
        ->adminDistrict->toBe('Bristol, City of')
        ->and($location?->postcode->formatted())->toBe('BS1 4DJ');

    $this->http->assertSent(fn (Request $request) => $request->url() === POSTCODES_BASE.'/postcodes/BS14DJ');
});

it('returns null for a well-formed postcode that does not exist', function () {
    $this->http->fake(['postcodes.test/*' => $this->http->response(fixtureBody('postcode-not-found'), 404)]);

    expect(postcodeLookup($this->http)->lookup('ZZ9 9ZZ'))->toBeNull();
});

it('rejects malformed postcodes without calling the API', function () {
    $this->http->fake();

    expect(fn () => postcodeLookup($this->http)->lookup('hello'))->toThrow(InvalidPostcode::class);

    $this->http->assertNothingSent();
});

it('rejects a result without coordinates', function () {
    $body = fixtureJson('postcode-found');
    $body['result']['latitude'] = null;
    $this->http->fake(['postcodes.test/*' => $this->http->response($body)]);

    expect(fn () => postcodeLookup($this->http)->lookup('BS1 4DJ'))->toThrow(UnexpectedResponse::class);
});
