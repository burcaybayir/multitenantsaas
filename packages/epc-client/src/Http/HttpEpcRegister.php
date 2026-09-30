<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InstallHub\EpcClient\Contracts\EpcRegister;
use InstallHub\EpcClient\Data\EpcCertificate;
use InstallHub\EpcClient\Data\EpcRecommendation;
use InstallHub\EpcClient\Data\EpcSearchResult;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;
use InstallHub\EpcClient\Support\Postcode;
use InvalidArgumentException;

/**
 * Client for https://epc.opendatacommunities.org (domestic register).
 */
final readonly class HttpEpcRegister implements EpcRegister
{
    public const SERVICE = 'EPC API';

    private const MAX_PAGE_SIZE = 5000;

    public function __construct(
        private Factory $http,
        private EpcConfig $config,
        private ResilientTransport $transport,
    ) {}

    public function searchByPostcode(Postcode|string $postcode, int $size = 100, ?string $searchAfter = null): EpcSearchResult
    {
        if ($size < 1 || $size > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf('Page size must be between 1 and %d.', self::MAX_PAGE_SIZE));
        }

        return $this->search(array_filter([
            'postcode' => Postcode::from($postcode)->compact(),
            'size' => $size,
            'search-after' => $searchAfter,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function searchByUprn(string $uprn): EpcSearchResult
    {
        if (! ctype_digit($uprn)) {
            throw new InvalidArgumentException('A UPRN is numeric.');
        }

        return $this->search(['uprn' => $uprn]);
    }

    public function certificate(string $lmkKey): ?EpcCertificate
    {
        $response = $this->get('domestic/certificate/'.$this->lmkKey($lmkKey));

        if ($response->status() === 404) {
            return null;
        }

        $rows = $this->rows($response);

        return $rows === [] ? null : EpcCertificate::fromApi($rows[0]);
    }

    public function recommendations(string $lmkKey): array
    {
        $response = $this->get('domestic/recommendations/'.$this->lmkKey($lmkKey));

        if ($response->status() === 404) {
            return [];
        }

        return array_map(EpcRecommendation::fromApi(...), $this->rows($response));
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function search(array $query): EpcSearchResult
    {
        $response = $this->get('domestic/search', $query);

        if ($response->status() === 404) {
            return EpcSearchResult::empty();
        }

        $cursor = $response->header('X-Next-Search-After');

        return new EpcSearchResult(
            certificates: array_map(EpcCertificate::fromApi(...), $this->rows($response)),
            nextSearchAfter: $cursor !== '' ? $cursor : null,
        );
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->transport->send(self::SERVICE, fn (): Response => $this->request()->get($path, $query));
    }

    private function request(): PendingRequest
    {
        [$email, $key] = $this->config->credentials();

        return $this->http
            ->baseUrl(rtrim($this->config->baseUrl, '/').'/')
            ->withBasicAuth($email, $key)
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);
    }

    /**
     * The API answers "no results" with 200 and an empty body rather than
     * an empty "rows" array, so both are treated as an empty list.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Response $response): array
    {
        if (trim($response->body()) === '') {
            return [];
        }

        $rows = $response->json('rows');

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw UnexpectedResponse::malformed(self::SERVICE, 'missing "rows" list');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw UnexpectedResponse::malformed(self::SERVICE, 'row is not an object');
            }
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * LMK keys go into the URL path, so only allow what a real key contains.
     */
    private function lmkKey(string $lmkKey): string
    {
        if (preg_match('/^[A-Za-z0-9]{1,64}$/', $lmkKey) !== 1) {
            throw new InvalidArgumentException('Invalid LMK key.');
        }

        return $lmkKey;
    }
}
