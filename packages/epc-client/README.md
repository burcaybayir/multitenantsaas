# installhub/epc-client

Typed PHP client for the UK **EPC Open Data** register
([epc.opendatacommunities.org](https://epc.opendatacommunities.org)) and
**[postcodes.io](https://postcodes.io)**, built on Laravel's HTTP client.

- Typed, immutable DTOs (`EpcCertificate`, `EpcRecommendation`, `PostcodeLocation`),
  a validated `Postcode` value object and an `EnergyRating` enum
- Retries with exponential backoff and full jitter, for connection errors, 429 and 5xx only
- Rate-limit aware: honours `Retry-After`, but fails fast with `RateLimitExceeded`
  instead of blocking a queue worker on long waits
- One exception hierarchy with `isRetryable()`, so callers know whether to retry or give up
- Works with `Http::fake()` in the host application's tests

## Installation

```bash
composer require installhub/epc-client
```

The service provider is auto-discovered. Configure with environment variables:

```dotenv
EPC_API_EMAIL=you@example.com
EPC_API_KEY=your-api-key
```

Other settings (base URLs, timeouts, retry policy): see `config/epc-client.php`;
publish it with `php artisan vendor:publish --tag=epc-client-config`.

## Usage

Depend on the interfaces, not the HTTP classes:

```php
use InstallHub\EpcClient\Contracts\EpcRegister;
use InstallHub\EpcClient\Contracts\PostcodeLookup;
use InstallHub\EpcClient\Enums\EnergyRating;

public function __construct(
    private EpcRegister $epc,
    private PostcodeLookup $postcodes,
) {}

$location = $this->postcodes->lookup('bs1 4dj');        // ?PostcodeLocation (null = not found)
$location?->latitude;                                    // 51.452293

$latest = $this->epc->searchByPostcode('BS1 4DJ')        // EpcSearchResult
    ->forUprn('100120123456')
    ->latest();                                          // most recently lodged ?EpcCertificate

$latest?->currentRating?->isAtLeast(EnergyRating::C);    // bool
$latest?->roofDescription;                               // "Pitched, 270 mm loft insulation"
$latest?->attributes['co2-emissions-current'];           // any unmapped column

// Pagination (the API uses a search-after cursor)
$page = $this->epc->searchByPostcode('BS1 4DJ', size: 500);
while ($page->hasMorePages()) {
    $page = $this->epc->searchByPostcode('BS1 4DJ', size: 500, searchAfter: $page->nextSearchAfter);
}
```

### Error handling

Everything thrown extends `InstallHub\EpcClient\Exceptions\ClientException`:

| Exception | When | `isRetryable()` |
|---|---|---|
| `InvalidPostcode` | Malformed postcode (no request sent) | no |
| `MissingCredentials` | EPC key/email not configured (no request sent) | no |
| `AuthenticationFailed` | 401 / 403 | no |
| `UnexpectedResponse` | Other 4xx, or a body without the documented shape | no |
| `RateLimitExceeded` | 429 with `Retry-After` > `max_retry_after_seconds` (has `retryAfterSeconds`) | yes |
| `ServiceUnavailable` | 5xx or connection failure after all retries | yes |

In a queued job:

```php
try {
    $result = $epc->searchByPostcode($postcode);
} catch (RateLimitExceeded $e) {
    $this->release($e->retryAfterSeconds ?? 60);
    return;
} catch (ClientException $e) {
    $e->isRetryable() ? throw $e : $this->fail($e);
}
```

## Data quality notes

The EPC dataset holds decades of assessor-entered data. Every value arrives as a
string, and missing data is spelled `""`, `"NO DATA!"` or `"INVALID!"`. The DTOs
turn all of those into `null`, and numbers and dates into proper types. A
property can have several certificates; use `EpcSearchResult::latest()`.
Certificates are valid for ten years (`EpcCertificate::isValidOn()`).

## Development

```bash
composer install
composer test      # Pest, HTTP faked, no network access needed
composer analyse   # PHPStan level 8
composer lint      # Pint
```
