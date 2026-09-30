<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

final readonly class PostcodesConfig
{
    public function __construct(
        public string $baseUrl,
        public float $timeout = 5.0,
        public float $connectTimeout = 3.0,
    ) {}
}
