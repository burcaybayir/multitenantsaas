<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

use InstallHub\EpcClient\Exceptions\MissingCredentials;
use SensitiveParameter;

final readonly class EpcConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $email,
        #[SensitiveParameter] private ?string $apiKey,
        public float $timeout = 10.0,
        public float $connectTimeout = 3.0,
    ) {}

    /**
     * @return array{0: string, 1: string}
     */
    public function credentials(): array
    {
        if ($this->email === null || $this->email === '' || $this->apiKey === null || $this->apiKey === '') {
            throw MissingCredentials::forEpc();
        }

        return [$this->email, $this->apiKey];
    }

    /**
     * Keep the API key out of dumps, logs and exception traces.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'email' => $this->email,
            'apiKey' => $this->apiKey === null ? null : '********',
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
        ];
    }
}
