<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

final class InvalidPostcode extends ClientException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UK postcode.', $value));
    }
}
