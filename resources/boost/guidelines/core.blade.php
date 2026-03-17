## KYC Core (asciisd/kyc-core)

Core KYC (Know Your Customer) verification framework for Laravel using a manager/driver pattern. Provides contracts, DTOs, enums, models, services, and a trait for building provider-specific KYC drivers (e.g., ShuftiPro, Jumio, Onfido).

### Key Contracts

- `KycDriverInterface` — Core driver contract: `createVerification()`, `createSimpleVerification()`, `retrieveVerification()`, `processWebhook()`, `validateWebhookSignature()`, `downloadDocuments()`, `mapEventToStatus()`.
- `KycDataTransformerInterface` — Maps raw provider data to standardized format: `transform()`, `canHandle()`, `getProviderName()`.

### Creating a KYC Driver

1. Implement `KycDriverInterface` with all required methods.
2. Register the driver class in `config/kyc.php` under `drivers`.
3. Implement a `KycDataTransformerInterface` for provider-specific data mapping.

@verbatim
<code-snippet name="Driver Registration" lang="php">
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
        ],
    ],
],
</code-snippet>
@endverbatim

### Usage

@verbatim
<code-snippet name="Using the Kyc Facade" lang="php">
use Asciisd\KycCore\Facades\Kyc;

// Simple verification
$response = Kyc::createSimpleVerification($user, ['country' => 'US']);

// Full verification
$request = new KycVerificationRequest(email: $user->email, country: 'US');
$response = Kyc::createVerification($user, $request);

// Process webhook
$response = Kyc::processWebhook($payload, $headers);
</code-snippet>
@endverbatim

### HasKycVerification Trait

Add `Asciisd\KycCore\Traits\HasKycVerification` to models that need KYC. Provides `kyc()`, `latestKyc()`, `canStartKyc()`, `needsKycVerification()`, `hasCompletedKyc()`, `canResumeKyc()`, `startKycProcess()`, `updateKycStatus()`.

### Reference Archiving

The `Kyc` model keeps a single record per user. When the `reference` is overwritten (new verification started), the old reference is archived into `previous_references` (JSON array) automatically by `updateKycStatus()` and `startKycProcess()`. The webhook handler (`KycManager::processWebhook()`) resolves records by: 1) current reference, 2) `previous_references` via `findByPreviousReference()`, 3) email-based fallback from webhook payload. Use `ownsReference($ref)` to check if a reference belongs to a record (current or archived).

### Importing Verifications

`KycManager::importVerification()` imports a verification result into an existing KYC record. Handles status mapping, importable validation, data building, reference archiving, `started_at` fallback, `updateKycStatus()`, and `populateFromKyc()` on completion. Throws `InvalidArgumentException` for non-importable statuses.

@verbatim
<code-snippet name="Import Verification" lang="php">
use Asciisd\KycCore\Facades\Kyc;

$status = Kyc::importVerification($kycModel, $reference, $kycVerificationResponse, [
    'imported_from_environment' => 'production',
    'notes' => 'Imported from production',
]);
</code-snippet>
@endverbatim

### Enums

- `KycStatusEnum` — NotStarted, RequestPending, InProgress, ReviewPending, VerificationCompleted, VerificationFailed, VerificationCancelled, RequestTimeout, Completed, Rejected.

### DTOs

- `KycVerificationRequest` — Verification request with email, country, language, redirect/callback URLs, journey ID.
- `KycVerificationResponse` — Response with reference, event, success, verification URL, extracted data, documents.
- `StandardizedKycData` — Normalized KYC data: name, DOB, gender, nationality, address, documents.

### Events

- `KycStatusChanged` — Dispatched on every status transition, carries `$user`, `$previousStatus`, `$newStatus`, and `$response`.
- `VerificationStarted` — Dispatched when verification begins.
- `VerificationCompleted` — Dispatched on successful verification (after `KycStatusChanged`).
- `VerificationFailed` — Dispatched on failed verification (after `KycStatusChanged`).

### Kyc Model

Polymorphic model (`kycable`) with fields: `driver`, `status`, `reference`, `previous_references` (JSON array), `started_at`, `completed_at`, `data` (JSON), `notes`.

Casts: `status` → `KycStatusEnum`, `started_at`/`completed_at` → datetime, `data` → array, `previous_references` → array.

Methods: `getActiveVerificationUrl()`, `canResumeKyc()`, `archiveCurrentReference()`, `findByPreviousReference()`, `ownsReference()`, `updateKycStatus()`, `startKycProcess()`, `updateKycData()`, `getDriver()`, `usesDriver()`, `isCompleted()`, `isFailed()`, `isInProgress()`, `needsAction()`.

### Config

Config file: `config/kyc.php`. Key sections: `default_driver`, `drivers` (class + supports), `settings` (max attempts, URL expiry, document storage, duplicate detection, resume functionality), `user_model` (class used for email-based webhook fallback lookup, defaults to `App\Models\User`).
