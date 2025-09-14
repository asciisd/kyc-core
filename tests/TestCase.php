<?php

namespace Asciisd\KycCore\Tests;

use Asciisd\KycCore\Providers\KycServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            KycServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup KYC configuration
        $app['config']->set('kyc', [
            'default_driver' => 'shuftipro',
            'drivers' => [
                'test' => [
                    'name' => 'Test Driver',
                    'description' => 'Test KYC Driver',
                    'enabled' => true,
                    'class' => TestDriver::class,
                    'supports' => [
                        'document_verification' => true,
                        'face_verification' => true,
                        'webhook_callbacks' => true,
                        'document_download' => true,
                    ],
                ],
                'shuftipro' => [
                    'name' => 'ShuftiPro Driver',
                    'description' => 'ShuftiPro KYC Driver',
                    'enabled' => true,
                    'class' => TestDriver::class,
                    'supports' => [
                        'document_verification' => true,
                        'face_verification' => true,
                        'webhook_callbacks' => true,
                        'document_download' => true,
                    ],
                ],
            ],
            'settings' => [
                'require_email_verification' => false,
                'max_verification_attempts' => 3,
                'verification_url_expiry_hours' => 24,
                'auto_download_documents' => true,
                'document_storage_disk' => 'local',
                'document_storage_path' => 'kyc/documents',
                'enable_duplicate_detection' => true,
                'webhook_signature_validation' => true,
            ],
            'supported_countries' => ['US', 'GB', 'CA'],
            'restricted_countries' => ['IR', 'KP'],
        ]);
    }
}

// Test driver for testing purposes
class TestDriver implements \Asciisd\KycCore\Contracts\KycDriverInterface
{
    public function createVerification(\Illuminate\Database\Eloquent\Model $user, \Asciisd\KycCore\DTOs\KycVerificationRequest $request): \Asciisd\KycCore\DTOs\KycVerificationResponse
    {
        return new \Asciisd\KycCore\DTOs\KycVerificationResponse(
            reference: 'test_ref_'.uniqid(),
            event: 'request.pending',
            success: true,
            verificationUrl: 'https://test.verification.url',
        );
    }

    public function createSimpleVerification(\Illuminate\Database\Eloquent\Model $user, array $options = []): \Asciisd\KycCore\DTOs\KycVerificationResponse
    {
        return $this->createVerification($user, new \Asciisd\KycCore\DTOs\KycVerificationRequest(
            email: $user->email ?? 'test@example.com'
        ));
    }

    public function retrieveVerification(string $reference): \Asciisd\KycCore\DTOs\KycVerificationResponse
    {
        return new \Asciisd\KycCore\DTOs\KycVerificationResponse(
            reference: $reference,
            event: 'verification.completed',
            success: true,
        );
    }

    public function canResumeVerification(string $reference): bool
    {
        return true;
    }

    public function getVerificationUrl(string $reference): ?string
    {
        return 'https://test.verification.url';
    }

    public function processWebhook(array $payload, array $headers = []): \Asciisd\KycCore\DTOs\KycVerificationResponse
    {
        return new \Asciisd\KycCore\DTOs\KycVerificationResponse(
            reference: $payload['reference'] ?? 'test_ref',
            event: $payload['event'] ?? 'verification.completed',
            success: true,
        );
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        return true;
    }

    public function downloadDocuments(\Illuminate\Database\Eloquent\Model $user, string $reference): array
    {
        return ['document1.jpg', 'document2.jpg'];
    }

    public function getConfig(): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getCapabilities(): array
    {
        return [
            'document_verification' => true,
            'face_verification' => true,
            'webhook_callbacks' => true,
            'document_download' => true,
        ];
    }
}
