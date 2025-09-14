# Asciisd KYC Core

A comprehensive Laravel package for KYC (Know Your Customer) verification management. This package provides a clean, extensible architecture for integrating multiple KYC providers.

## Features

- **Driver-based Architecture**: Support for multiple KYC providers (ShuftiPro, Jumio, Onfido, etc.)
- **Event-driven**: Fires events for verification lifecycle management
- **Flexible Status Management**: Comprehensive status tracking and transitions
- **Document Management**: Built-in document storage and retrieval
- **Webhook Support**: Secure webhook handling with signature validation
- **Validation**: Built-in validation for requests and user eligibility
- **Logging**: Comprehensive logging for debugging and monitoring
- **Morphable Models**: Works with any Eloquent model using morphable relationships

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

## Driver Architecture

The package uses a driver-based architecture, making it easy to add new KYC providers:

```php
interface KycDriverInterface
{
    public function createVerification(Model $user, KycVerificationRequest $request): KycVerificationResponse;
    public function retrieveVerification(string $reference): KycVerificationResponse;
    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse;
    public function downloadDocuments(Model $user, string $reference): array;
    public function getName(): string;
    public function isEnabled(): bool;
    public function getCapabilities(): array;
}
```

## Configuration

The package configuration allows you to:

- Set default driver
- Configure multiple drivers
- Set verification settings
- Define supported/restricted countries
- Configure document storage

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
