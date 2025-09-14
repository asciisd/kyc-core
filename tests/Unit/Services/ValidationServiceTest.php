<?php

namespace Asciisd\KycCore\Tests\Unit\Services;

use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\ValidationService;
use Asciisd\KycCore\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationService = $this->app->make(ValidationService::class);
    }

    public function test_validate_user_with_verified_email()
    {
        $user = $this->createTestUserWithVerifiedEmail();

        // Should not throw exception
        $this->validationService->validateUser($user);
        $this->assertTrue(true);
    }

    public function test_validate_user_without_verified_email_throws_exception()
    {
        $this->app['config']->set('kyc.settings.require_email_verification', true);

        $user = $this->createTestUserWithoutVerifiedEmail();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email must be verified before starting KYC verification.');

        $this->validationService->validateUser($user);
    }

    public function test_validate_user_with_max_attempts_reached()
    {
        $user = $this->createTestUserWithMaxAttempts();

        // Debug: Check if KYC records were created
        $kycCount = Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->whereIn('status', [
                KycStatusEnum::VerificationFailed,
                KycStatusEnum::Rejected,
                KycStatusEnum::RequestTimeout,
            ])
            ->count();
        
        $this->assertEquals(3, $kycCount, 'Expected 3 KYC records to be created');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Maximum KYC verification attempts exceeded.');

        $this->validationService->validateUser($user);
    }

    public function test_validate_request_with_valid_data()
    {
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            country: 'US'
        );

        // Should not throw exception
        $this->validationService->validateRequest($request);
        $this->assertTrue(true);
    }

    public function test_validate_request_with_empty_email()
    {
        $request = new KycVerificationRequest(email: '');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email is required for KYC verification.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_invalid_email()
    {
        $request = new KycVerificationRequest(email: 'invalid-email');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email format.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_invalid_country()
    {
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            country: 'INVALID'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Country code must be 2 characters long.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_unsupported_country()
    {
        $this->app['config']->set('kyc.supported_countries', ['US', 'GB']);

        $request = new KycVerificationRequest(
            email: 'test@example.com',
            country: 'FR'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Country is not supported for KYC verification.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_restricted_country()
    {
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            country: 'IR'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Country is restricted for KYC verification.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_invalid_allowed_countries()
    {
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            allowedCountries: ['INVALID', 'XX']
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Country code must be 2 characters long.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_request_with_invalid_denied_countries()
    {
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            deniedCountries: ['INVALID']
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Country code must be 2 characters long.');

        $this->validationService->validateRequest($request);
    }

    public function test_validate_webhook_payload_with_valid_data()
    {
        $payload = [
            'reference' => 'test_ref_123',
            'event' => 'verification.completed',
        ];

        // Should not throw exception
        $this->validationService->validateWebhookPayload($payload);
        $this->assertTrue(true);
    }

    public function test_validate_webhook_payload_with_empty_payload()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Webhook payload cannot be empty.');

        $this->validationService->validateWebhookPayload([]);
    }

    public function test_validate_webhook_payload_without_reference()
    {
        $payload = [
            'event' => 'verification.completed',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reference is required in webhook payload.');

        $this->validationService->validateWebhookPayload($payload);
    }

    public function test_validate_webhook_payload_without_event()
    {
        $payload = [
            'reference' => 'test_ref_123',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Event is required in webhook payload.');

        $this->validationService->validateWebhookPayload($payload);
    }

    public function test_validate_redirect_url_with_valid_url()
    {
        // Should not throw exception
        $this->validationService->validateRedirectUrl('https://example.com/redirect');
        $this->assertTrue(true);
    }

    public function test_validate_redirect_url_with_invalid_url()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid redirect URL format.');

        $this->validationService->validateRedirectUrl('invalid-url');
    }

    public function test_validate_callback_url_with_valid_url()
    {
        // Should not throw exception
        $this->validationService->validateCallbackUrl('https://example.com/callback');
        $this->assertTrue(true);
    }

    public function test_validate_callback_url_with_invalid_url()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid callback URL format.');

        $this->validationService->validateCallbackUrl('invalid-url');
    }

    private function createTestUserWithVerifiedEmail(): Model
    {
        return new class extends Model
        {
            protected $table = 'users';

            public function getKey()
            {
                return 1;
            }

            public function getMorphClass()
            {
                return 'User';
            }

            public function hasVerifiedEmail(): bool
            {
                return true;
            }
        };
    }

    private function createTestUserWithoutVerifiedEmail(): Model
    {
        return new class extends Model
        {
            protected $table = 'users';

            public function getKey()
            {
                return 1;
            }

            public function getMorphClass()
            {
                return 'User';
            }

            public function hasVerifiedEmail(): bool
            {
                return false;
            }
        };
    }

    private function createTestUserWithMaxAttempts(): Model
    {
        // Create KYC records that count as attempts
        for ($i = 0; $i < 3; $i++) {
            Kyc::create([
                'kycable_id' => 1,
                'kycable_type' => 'User',
                'reference' => "test_ref_{$i}",
                'status' => KycStatusEnum::VerificationFailed,
            ]);
        }

        return new class extends Model
        {
            protected $table = 'users';

            public function getKey()
            {
                return 1;
            }

            public function hasVerifiedEmail(): bool
            {
                return true;
            }

            public function getMorphClass()
            {
                return 'User';
            }
        };
    }
}
