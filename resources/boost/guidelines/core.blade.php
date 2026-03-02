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

### Enums

- `KycStatusEnum` — NotStarted, RequestPending, InProgress, ReviewPending, VerificationCompleted, VerificationFailed, VerificationCancelled, RequestTimeout, Completed, Rejected.

### DTOs

- `KycVerificationRequest` — Verification request with email, country, language, redirect/callback URLs, journey ID.
- `KycVerificationResponse` — Response with reference, event, success, verification URL, extracted data, documents.
- `StandardizedKycData` — Normalized KYC data: name, DOB, gender, nationality, address, documents.

### Events

- `VerificationStarted` — Dispatched when verification begins.
- `VerificationCompleted` — Dispatched on successful verification.
- `VerificationFailed` — Dispatched on failed verification.

### Config

Config file: `config/kyc.php`. Key sections: `default_driver`, `drivers` (class + supports), `settings` (max attempts, URL expiry, document storage, duplicate detection, resume functionality).
