<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Contracts;

use InstallHub\EpcClient\Data\PostcodeLocation;
use InstallHub\EpcClient\Exceptions\ClientException;
use InstallHub\EpcClient\Support\Postcode;

interface PostcodeLookup
{
    /**
     * Null when the postcode is well-formed but doesn't exist (or was retired).
     *
     * @throws ClientException
     */
    public function lookup(Postcode|string $postcode): ?PostcodeLocation;
}
