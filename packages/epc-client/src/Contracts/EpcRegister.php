<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Contracts;

use InstallHub\EpcClient\Data\EpcCertificate;
use InstallHub\EpcClient\Data\EpcRecommendation;
use InstallHub\EpcClient\Data\EpcSearchResult;
use InstallHub\EpcClient\Exceptions\ClientException;
use InstallHub\EpcClient\Support\Postcode;

/**
 * Domestic EPC register. Depend on this interface, not the HTTP class, so
 * consumers can swap in a fake or a caching decorator.
 */
interface EpcRegister
{
    /**
     * @param  int  $size  1-5000 (API maximum)
     * @param  string|null  $searchAfter  cursor from a previous result's nextSearchAfter
     *
     * @throws ClientException
     */
    public function searchByPostcode(Postcode|string $postcode, int $size = 100, ?string $searchAfter = null): EpcSearchResult;

    /**
     * @throws ClientException
     */
    public function searchByUprn(string $uprn): EpcSearchResult;

    /**
     * @throws ClientException
     */
    public function certificate(string $lmkKey): ?EpcCertificate;

    /**
     * @return list<EpcRecommendation>
     *
     * @throws ClientException
     */
    public function recommendations(string $lmkKey): array;
}
