<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

final class AuthenticationFailed extends ClientException
{
    public static function for(string $service, int $status): self
    {
        return new self(sprintf('%s rejected the credentials (HTTP %d).', $service, $status));
    }
}
