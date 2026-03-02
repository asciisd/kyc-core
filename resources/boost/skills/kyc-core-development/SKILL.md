---
name: kyc-core-development
description: "Build KYC verification drivers, transformers, and integrations with the asciisd/kyc-core package. Activates when creating or extending KYC drivers, implementing KycDriverInterface or KycDataTransformerInterface, working with KycVerificationRequest/KycVerificationResponse DTOs, StandardizedKycData, KycStatusEnum, configuring kyc.php, handling KYC webhooks, using the Kyc facade or KycManager, adding the HasKycVerification trait, or working with the Kyc model."
---

# KYC Core Development

## Package Overview

`asciisd/kyc-core` is a flexible KYC (Know Your Customer) verification framework for Laravel using a manager/driver pattern. It provides contracts, DTOs, enums, models, services, and a trait for building provider-specific KYC drivers (e.g., ShuftiPro, Jumio, Onfido).

**Namespace:** `Asciisd\KycCore`

## Architecture

```
Contracts/
├── KycDriverInterface          — Core driver contract (verify, webhook, documents)
└── KycDataTransformerInterface — Maps raw provider data to standardized format

DTOs/
├── KycVerificationRequest      — Verification request DTO
├── KycVerificationResponse     — Verification response DTO
└── StandardizedKycData         — Normalized KYC data across providers

Enums/
└── KycStatusEnum               — NotStarted, InProgress, Completed, Failed, etc.

Events/
├── VerificationStarted         — Dispatched when verification begins
├── VerificationCompleted       — Dispatched on successful verification
└── VerificationFailed          — Dispatched on failed verification

Facades/
└── Kyc                         — Facade for KycManager

Http/Controllers/
└── KycWebhookController        — Webhook + completion endpoints

Models/
└── Kyc                         — Polymorphic model (kycable) with status, data, driver

Providers/
└── KycServiceProvider           — Registers bindings, routes, config, migrations

Services/
├── KycManager                  — Main manager (extends Illuminate\Support\Manager)
├── KycTransformerFactory       — Discovers and manages data transformers
├── StatusService               — Updates statuses, fires events
└── ValidationService           — Validates users, requests, webhooks

Traits/
└── HasKycVerification          — Add to User model for KYC functionality

Transformers/
└── GenericTransformer          — Fallback transformer for any provider
```

## When to Use This Skill

Use this skill when:
- Creating or modifying KYC driver implementations
- Working with KYC verification flows (start, resume, webhook)
- Handling KYC webhook events (`VerificationCompleted`, `VerificationFailed`)
- Implementing data transformers for KYC providers
- Configuring `kyc.php` or adding new KYC drivers
- Working with the `Kyc` model or `HasKycVerification` trait
- Debugging KYC verification or webhook issues
- Writing tests for KYC flows

## Verification Flow

1. Call `Kyc::createSimpleVerification($user)` or `Kyc::createVerification($user, $request)`
2. Returns `KycVerificationResponse` with `reference` and `verificationUrl`
3. Redirect user to the verification URL
4. User completes identity verification on provider's hosted page
5. Provider sends webhook to `api/kyc/webhook`
6. `KycWebhookController` validates, parses, dispatches events
7. `KycManager` updates KYC model status and data
8. Application listeners handle `VerificationCompleted` / `VerificationFailed`

## Creating a KYC Driver

### Step 1: Implement KycDriverInterface

```php
namespace App\Drivers;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Illuminate\Database\Eloquent\Model;

class MyProviderDriver implements KycDriverInterface
{
    public function createVerification(Model $user, KycVerificationRequest $request): KycVerificationResponse
    {
        // Call provider API to create verification session
    }

    public function createSimpleVerification(Model $user, array $options = []): KycVerificationResponse
    {
        // Simplified verification using config defaults
    }

    public function retrieveVerification(string $reference): KycVerificationResponse
    {
        // Retrieve verification status from provider
    }

    public function canResumeVerification(string $reference): bool
    {
        // Check if an incomplete verification can be resumed
    }

    public function getVerificationUrl(string $reference): ?string
    {
        // Get the hosted verification page URL
    }

    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse
    {
        // Parse and process incoming webhook
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        // Verify webhook authenticity
    }

    public function downloadDocuments(Model $user, string $reference): array
    {
        // Download and store verification documents
    }

    public function mapEventToStatus(string $event): KycStatusEnum
    {
        return match ($event) {
            'verification.accepted' => KycStatusEnum::Completed,
            'verification.declined' => KycStatusEnum::Rejected,
            'request.pending' => KycStatusEnum::RequestPending,
            'request.timeout' => KycStatusEnum::RequestTimeout,
            default => KycStatusEnum::InProgress,
        };
    }

    public function getConfig(): array { return config('kyc.drivers.my-provider', []); }
    public function getName(): string { return 'my-provider'; }
    public function isEnabled(): bool { return $this->getConfig()['enabled'] ?? false; }
    public function getCapabilities(): array { return $this->getConfig()['supports'] ?? []; }
}
```

### Step 2: Register in Config

```php
// config/kyc.php
'drivers' => [
    'my-provider' => [
        'name' => 'My Provider',
        'description' => 'My KYC Provider',
        'enabled' => env('MY_PROVIDER_ENABLED', true),
        'class' => \App\Drivers\MyProviderDriver::class,
        'supports' => [
            'document_verification' => true,
            'face_verification' => true,
            'address_verification' => false,
        ],
    ],
],
```

### Step 3: Implement Data Transformer

```php
namespace App\Transformers;

use Asciisd\KycCore\Contracts\KycDataTransformerInterface;

class MyProviderTransformer implements KycDataTransformerInterface
{
    public function transform(array $rawData): array
    {
        // Map provider-specific data to StandardizedKycData format
        return [
            'first_name' => $rawData['given_name'] ?? null,
            'last_name' => $rawData['family_name'] ?? null,
            'date_of_birth' => $rawData['dob'] ?? null,
            'country' => $rawData['country_code'] ?? null,
            'documents' => $rawData['docs'] ?? null,
        ];
    }

    public function canHandle(array $rawData): bool
    {
        return isset($rawData['provider']) && $rawData['provider'] === 'my-provider';
    }

    public function getProviderName(): string
    {
        return 'my-provider';
    }
}
```

## Using the Kyc Facade

### Simple Verification (Recommended)

```php
use Asciisd\KycCore\Facades\Kyc;

$response = Kyc::createSimpleVerification($user, [
    'country' => 'US',
    'language' => 'en',
]);

if ($response->hasVerificationUrl()) {
    return redirect($response->verificationUrl);
}
```

### Full Verification

```php
use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\Facades\Kyc;

$request = new KycVerificationRequest(
    email: $user->email,
    country: 'US',
    language: 'en',
    redirectUrl: route('kyc.complete'),
    callbackUrl: route('kyc.webhook'),
    journeyId: config('shuftipro.idv_journeys.default_journey_id'),
);

$response = Kyc::createVerification($user, $request);
```

### Resume Verification

```php
use Asciisd\KycCore\Facades\Kyc;

if ($user->canResumeKyc()) {
    $url = $user->getActiveVerificationUrl();
    return redirect($url);
}
```

## HasKycVerification Trait

Add to any model (typically `User`) that needs KYC verification:

```php
use Asciisd\KycCore\Traits\HasKycVerification;

class User extends Authenticatable
{
    use HasKycVerification;
}
```

Provides: `kyc()`, `latestKyc()`, `canStartKyc()`, `needsKycVerification()`, `hasCompletedKyc()`, `canResumeKyc()`, `getActiveVerificationUrl()`, `getKycStatus()`, `startKycProcess()`, `updateKycStatus()`, `updateKycData()`, `getKycReference()`, `hasKyc()`, `getKycDriver()`.

## Handling Webhooks

### Event Listeners

```php
use Asciisd\KycCore\Events\VerificationCompleted;
use Asciisd\KycCore\Events\VerificationFailed;

class HandleVerificationCompleted
{
    public function handle(VerificationCompleted $event): void
    {
        $user = $event->user;
        $reference = $event->reference;
        $response = $event->response;

        if ($response->isSuccessful()) {
            // Approve user, update status, etc.
        }
    }
}
```

### Webhook Routes (auto-registered)

| Route | Method | Purpose |
|-------|--------|---------|
| `api/kyc/webhook` | POST | Main webhook handler |
| `api/kyc/webhook/callback` | POST | Alternative webhook endpoint |
| `api/kyc/verification/complete` | GET | Verification completion callback |
| `api/kyc/health` | GET | Health check |

## KycVerificationResponse Properties

| Property | Type | Description |
|----------|------|-------------|
| `reference` | `string` | Provider reference ID |
| `event` | `string` | Event type from provider |
| `success` | `bool` | Whether verification succeeded |
| `verificationUrl` | `?string` | Hosted verification page URL |
| `extractedData` | `?array` | Extracted identity data |
| `verificationResults` | `?array` | Detailed verification results |
| `documentImages` | `?array` | Document image URLs |
| `country` | `?string` | Detected country |
| `duplicateDetected` | `?bool` | Duplicate account flag |
| `declineReason` | `?string` | Reason for decline |

Methods: `isSuccessful()`, `isPending()`, `isCompleted()`, `isFailed()`, `hasVerificationUrl()`, `hasDocuments()`.

## KycStatusEnum

| Case | Helpers |
|------|---------|
| `NotStarted` | `canStartIdentityVerification()` |
| `RequestPending` | `isInProgress()`, `canBeResumed()` |
| `InProgress` | `isInProgress()`, `canBeResumed()` |
| `ReviewPending` | `isInProgress()` |
| `VerificationCompleted` | `isCompleted()` |
| `VerificationFailed` | `isFailed()`, `canStartIdentityVerification()` |
| `VerificationCancelled` | `isFailed()`, `canStartIdentityVerification()` |
| `RequestTimeout` | `isFailed()`, `canStartIdentityVerification()` |
| `Completed` | `isCompleted()` |
| `Rejected` | `isFailed()` |

## StandardizedKycData

Normalized DTO for KYC data across all providers:

Properties: `firstName`, `middleName`, `lastName`, `dateOfBirth`, `gender`, `nationality`, `country`, `placeOfBirth`, `address`, `city`, `state`, `postalCode`, `phoneNumber`, `email`, `documents`, `additionalData`.

Methods: `toArray()`, `fromArray()`, `hasData()`.

## KycTransformerFactory

Discovers and manages data transformers. Registers provider-specific transformers from config and falls back to `GenericTransformer`:

```php
use Asciisd\KycCore\Services\KycTransformerFactory;

$factory = app(KycTransformerFactory::class);
$transformed = $factory->transform($rawWebhookData);
```

## Configuration

Config file: `config/kyc.php`

| Key | Description |
|-----|-------------|
| `default_driver` | Default KYC driver name |
| `drivers` | Driver registrations (class, supports, enabled) |
| `settings.max_verification_attempts` | Max verification attempts per user |
| `settings.verification_url_expiry_hours` | URL expiry in hours |
| `settings.auto_download_documents` | Auto-download verification documents |
| `settings.document_storage_disk` | Storage disk for documents |
| `settings.enable_duplicate_detection` | Detect duplicate accounts |
| `settings.enable_resume_functionality` | Allow resuming incomplete verifications |
| `settings.max_resume_attempts` | Max resume attempts |

Publish config: `php artisan vendor:publish --tag=kyc-config`

## Kyc Model

Polymorphic model (`kycable`) with fields: `driver`, `status`, `reference`, `started_at`, `completed_at`, `data` (JSON), `notes`.

Casts: `status` → `KycStatusEnum`, `started_at`/`completed_at` → datetime, `data` → array.

Methods: `getActiveVerificationUrl()`, `canResumeKyc()`, `updateKycStatus()`, `startKycProcess()`, `updateKycData()`, `getDriver()`, `usesDriver()`, `isCompleted()`, `isFailed()`, `isInProgress()`, `needsAction()`.

## Migrations

Table `kycs` with: `id`, `kycable_id`, `kycable_type`, `driver`, `status`, `reference`, `started_at`, `completed_at`, `data` (json), `notes`, `timestamps`.
