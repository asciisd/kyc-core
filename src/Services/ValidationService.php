<?php

namespace Asciisd\KycCore\Services;

use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ValidationService
{
    /**
     * Validate user before starting KYC
     */
    public function validateUser(Model $user): void
    {
        $requireEmailVerification = config('kyc.settings.require_email_verification', true);

        if ($requireEmailVerification && method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email must be verified before starting KYC verification.'],
            ]);
        }

        // Check if user has reached max attempts
        $maxAttempts = config('kyc.settings.max_verification_attempts', 3);
        $attempts = $this->getUserAttemptCount($user);

        if ($attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'kyc' => ['Maximum KYC verification attempts exceeded.'],
            ]);
        }
    }

    /**
     * Validate verification request
     */
    public function validateRequest(KycVerificationRequest $request): void
    {
        if (empty($request->email)) {
            throw ValidationException::withMessages([
                'email' => ['Email is required for KYC verification.'],
            ]);
        }

        if (! filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email format.'],
            ]);
        }

        // Validate country if provided
        if ($request->country) {
            $this->validateCountry($request->country);
        }

        // Validate allowed countries if provided
        if ($request->allowedCountries) {
            foreach ($request->allowedCountries as $country) {
                $this->validateCountry($country);
            }
        }

        // Validate denied countries if provided
        if ($request->deniedCountries) {
            foreach ($request->deniedCountries as $country) {
                $this->validateCountry($country);
            }
        }
    }

    /**
     * Validate country code
     */
    private function validateCountry(string $country): void
    {
        if (strlen($country) !== 2) {
            throw ValidationException::withMessages([
                'country' => ['Country code must be 2 characters long.'],
            ]);
        }

        $supportedCountries = config('kyc.supported_countries', []);
        $restrictedCountries = config('kyc.restricted_countries', []);

        if (! empty($supportedCountries) && ! in_array(strtoupper($country), $supportedCountries)) {
            throw ValidationException::withMessages([
                'country' => ['Country is not supported for KYC verification.'],
            ]);
        }

        if (in_array(strtoupper($country), $restrictedCountries)) {
            throw ValidationException::withMessages([
                'country' => ['Country is restricted for KYC verification.'],
            ]);
        }
    }

    /**
     * Get user's KYC attempt count
     */
    private function getUserAttemptCount(Model $user): int
    {
        return \Asciisd\KycCore\Models\Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->whereIn('status', [
                \Asciisd\KycCore\Enums\KycStatusEnum::VerificationFailed,
                \Asciisd\KycCore\Enums\KycStatusEnum::Rejected,
                \Asciisd\KycCore\Enums\KycStatusEnum::RequestTimeout,
            ])
            ->count();
    }

    /**
     * Validate webhook payload
     */
    public function validateWebhookPayload(array $payload): void
    {
        if (empty($payload)) {
            throw ValidationException::withMessages([
                'payload' => ['Webhook payload cannot be empty.'],
            ]);
        }

        if (! isset($payload['reference'])) {
            throw ValidationException::withMessages([
                'reference' => ['Reference is required in webhook payload.'],
            ]);
        }

        if (! isset($payload['event'])) {
            throw ValidationException::withMessages([
                'event' => ['Event is required in webhook payload.'],
            ]);
        }
    }

    /**
     * Validate redirect URL
     */
    public function validateRedirectUrl(?string $url): void
    {
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'redirect_url' => ['Invalid redirect URL format.'],
            ]);
        }
    }

    /**
     * Validate callback URL
     */
    public function validateCallbackUrl(?string $url): void
    {
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'callback_url' => ['Invalid callback URL format.'],
            ]);
        }
    }
}
