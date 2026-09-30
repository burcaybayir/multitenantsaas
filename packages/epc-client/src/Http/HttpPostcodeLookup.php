<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use InstallHub\EpcClient\Contracts\PostcodeLookup;
use InstallHub\EpcClient\Data\PostcodeLocation;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;
use InstallHub\EpcClient\Support\Postcode;

/**
 * Client for https://postcodes.io.
 */
final readonly class HttpPostcodeLookup implements PostcodeLookup
{
    public const SERVICE = 'postcodes.io';

    public function __construct(
        private Factory $http,
        private PostcodesConfig $config,
        private ResilientTransport $transport,
    ) {}

    public function lookup(Postcode|string $postcode): ?PostcodeLocation
    {
        $postcode = Postcode::from($postcode);

        $response = $this->transport->send(self::SERVICE, fn (): Response => $this->http
            ->baseUrl(rtrim($this->config->baseUrl, '/').'/')
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout)
            ->get('postcodes/'.rawurlencode($postcode->compact())));

        if ($response->status() === 404) {
            return null;
        }

        $result = $response->json('result');

        if (! is_array($result)) {
            throw UnexpectedResponse::malformed(self::SERVICE, 'missing "result" object');
        }

        /** @var array<string, mixed> $result */
        return PostcodeLocation::fromApi($result);
    }
}
