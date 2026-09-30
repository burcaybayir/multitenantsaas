<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

final class MissingCredentials extends ClientException
{
    public static function forEpc(): self
    {
        return new self('EPC API credentials are not configured (EPC_API_EMAIL / EPC_API_KEY).');
    }
}
