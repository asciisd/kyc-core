# Asciisd KYC Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asciisd/kyc-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/kyc-core)
[![Total Downloads](https://img.shields.io/packagist/dt/asciisd/kyc-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/kyc-core)
[![License](https://img.shields.io/packagist/l/asciisd/kyc-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/kyc-core)

A comprehensive Laravel package for KYC (Know Your Customer) verification management. This package provides a clean, extensible architecture for integrating multiple KYC providers with **automatic infrastructure routes** and **provider-agnostic status mapping**.

## ✨ Key Features

-   **🚀 Zero-Config Infrastructure**: Webhook routes automatically registered - no setup required!
-   **🔄 Provider-Agnostic Architecture**: Seamlessly switch between KYC providers (ShuftiPro, Jumio, Onfido, etc.)
-   **🎯 Driver-Based Status Mapping**: Each provider handles its own event-to-status mapping
-   **📡 Auto-Registered Routes**: Infrastructure routes work out-of-the-box
-   **🎪 Event-Driven**: Comprehensive event system for verification lifecycle
-   **📊 Smart Status Management**: Intelligent status tracking and transitions
-   **📁 Document Management**: Built-in document storage and retrieval
-   **🔒 Secure Webhooks**: Signature validation and comprehensive logging
-   **✅ Built-in Validation**: Request validation and user eligibility checks
-   **🔍 Comprehensive Logging**: Detailed logging for debugging and monitoring
-   **🔗 Morphable Models**: Works with any Eloquent model using morphable relationships

## Installation

```bash
composer require asciisd/kyc-core
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=kyc-config
```

Publish the migrations:

```bash
php artisan vendor:publish --tag=kyc-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## 🚀 Automatic Infrastructure Routes

**NEW!** The package now automatically registers infrastructure routes - no manual setup required!

### Auto-Registered Routes

When you install the package, these routes are automatically available:

```php
// Webhook endpoints (no authentication required)
POST   /api/kyc/webhook                 // Main webhook handler
POST   /api/kyc/webhook/callback        // Alternative webhook endpoint
GET    /api/kyc/verification/complete   // Verification completion callback
GET    /api/kyc/health                  // Health check endpoint

// Backward compatibility (without /api prefix)
POST   /kyc/webhook
POST   /kyc/webhook/callback
GET    /kyc/verification/complete
GET    /kyc/health
```

### Benefits

-   ✅ **Zero Configuration** - Works immediately after installation
-   ✅ **Consistent Behavior** - Same webhook handling across all applications
-   ✅ **Provider Agnostic** - Works with any KYC driver
-   ✅ **Automatic Updates** - Bug fixes and improvements benefit all apps
-   ✅ **Simplified Integration** - Focus on business logic, not infrastructure

### Health Check

Monitor your KYC system health:

```bash
curl https://yourdomain.com/api/kyc/health
```

```json
{
    "success": true,
    "message": "KYC infrastructure is healthy",
    "default_driver": "shuftipro",
    "available_drivers": ["shuftipro", "jumio", "onfido"],
    "enabled_drivers": ["shuftipro"]
}
```

## 🚀 Quick Start

### Minimal Setup (3 Steps!)

1. **Install the package**

    ```bash
    composer require asciisd/kyc-core asciisd/kyc-shuftipro
    ```

2. **Publish and run migrations**

    ```bash
    php artisan vendor:publish --tag=kyc-migrations
    php artisan migrate
    ```

3. **Add trait to your User model**

    ```php
    use Asciisd\KycCore\Traits\HasKycVerification;

    class User extends Model
    {
        use HasKycVerification;
    }
    ```

**That's it!** 🎉 Infrastructure routes are automatically registered. Just configure your KYC provider credentials and start verifying users.

## Usage

### Basic Usage

```php
use Asciisd\KycCore\Facades\Kyc;
use Asciisd\KycCore\DTOs\KycVerificationRequest;

// Create a verification request
$request = new KycVerificationRequest(
    email: 'user@example.com',
    country: 'US',
    language: 'en'
);

// Start verification
$response = Kyc::createVerification($user, $request);

// Process webhook
$webhookResponse = Kyc::processWebhook($payload, $headers);
```

### Model Integration

Add the trait to your User model:

```php
use Asciisd\KycCore\Traits\HasKycVerification;

class User extends Model
{
    use HasKycVerification;

    // Your model code...
}
```

Now you can use KYC methods on your user:

```php
// Check if user can start KYC
if ($user->canStartKyc()) {
    // Start verification process
}

// Check if user has completed KYC
if ($user->hasCompletedKyc()) {
    // User is verified
}

// Get current KYC status
$status = $user->getKycStatus();
```

### Events

The package fires several events you can listen to:

```php
use Asciisd\KycCore\Events\VerificationStarted;
use Asciisd\KycCore\Events\VerificationCompleted;
use Asciisd\KycCore\Events\VerificationFailed;

// Listen for events
Event::listen(VerificationStarted::class, function ($event) {
    Log::info('Verification started for user: ' . $event->user->id);
});
```

### Status Management

The package includes a comprehensive status enum:

```php
use Asciisd\KycCore\Enums\KycStatusEnum;

// Check status properties
if ($status->isCompleted()) {
    // Handle completed verification
}

if ($status->needsAction()) {
    // User needs to take action
}

// Get human-readable label
$label = $status->label(); // "KYC Completed"
$color = $status->color(); // "green"
```

## 🏗️ Advanced Driver Architecture

The package uses a sophisticated driver-based architecture with **provider-specific status mapping**:

```php
interface KycDriverInterface
{
    // Core verification methods
    public function createVerification(Model $user, KycVerificationRequest $request): KycVerificationResponse;
    public function retrieveVerification(string $reference): KycVerificationResponse;
    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse;
    public function downloadDocuments(Model $user, string $reference): array;

    // Driver information
    public function getName(): string;
    public function isEnabled(): bool;
    public function getCapabilities(): array;

    // 🆕 NEW: Provider-specific status mapping
    public function mapEventToStatus(string $event): KycStatusEnum;
}
```

### 🎯 Provider-Specific Status Mapping

Each driver handles its own event-to-status mapping, making the system truly provider-agnostic:

```php
// ShuftiPro Driver
public function mapEventToStatus(string $event): KycStatusEnum
{
    return match ($event) {
        'verification.completed' => KycStatusEnum::Completed,
        'verification.declined' => KycStatusEnum::Rejected,
        'verification.pending' => KycStatusEnum::InProgress,
        // ... ShuftiPro-specific events
    };
}

// Jumio Driver (example)
public function mapEventToStatus(string $event): KycStatusEnum
{
    return match ($event) {
        'SUCCESS' => KycStatusEnum::Completed,
        'ERROR' => KycStatusEnum::VerificationFailed,
        'INITIATED' => KycStatusEnum::InProgress,
        // ... Jumio-specific events
    };
}
```

### Benefits

-   ✅ **Provider Independence** - Each provider handles its own event mapping
-   ✅ **Easy Migration** - Switch providers without changing application logic
-   ✅ **Extensible** - Add new providers by implementing the interface
-   ✅ **Consistent** - Standardized KYC status across all providers

## Configuration

The package configuration allows you to:

-   Set default driver
-   Configure multiple drivers
-   Set verification settings
-   Define supported/restricted countries
-   Configure document storage

```php
// config/kyc.php
return [
    'default_driver' => 'shuftipro',
    'drivers' => [
        'shuftipro' => [
            'enabled' => true,
            'class' => 'Asciisd\\KycShuftiPro\\Drivers\\ShuftiProDriver',
            // ...
        ],
    ],
    'settings' => [
        'require_email_verification' => true,
        'max_verification_attempts' => 3,
        // ...
    ],
];
```

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
