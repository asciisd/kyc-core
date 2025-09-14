<?php

namespace Asciisd\KycCore\Traits;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;

trait HasKycVerification
{
    /**
     * Get the user's KYC verification.
     */
    public function kyc(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Kyc::class, 'kycable');
    }

    /**
     * Check if user can start KYC process
     */
    public function canStartKyc(): bool
    {
        if (method_exists($this, 'hasVerifiedEmail') && ! $this->hasVerifiedEmail()) {
            return false;
        }

        $kyc = $this->kyc;

        return $kyc === null || $kyc->status->canStartIdentityVerification();
    }

    /**
     * Check if user needs to complete KYC verification
     */
    public function needsKycVerification(): bool
    {
        $kyc = $this->kyc;

        return $kyc === null || $kyc->status->needsAction();
    }

    /**
     * Check if user has completed KYC
     */
    public function hasCompletedKyc(): bool
    {
        $kyc = $this->kyc;

        return $kyc?->status->isCompleted() ?? false;
    }

    /**
     * Check if user can resume existing KYC verification
     */
    public function canResumeKyc(): bool
    {
        $kyc = $this->kyc;

        return $kyc?->canResumeKyc() ?? false;
    }

    /**
     * Get active verification URL for user
     */
    public function getActiveVerificationUrl(): ?string
    {
        $kyc = $this->kyc;

        return $kyc?->getActiveVerificationUrl();
    }

    /**
     * Get user's current KYC status
     */
    public function getKycStatus(): ?KycStatusEnum
    {
        $kyc = $this->kyc;

        return $kyc?->status;
    }

    /**
     * Start KYC process for user
     */
    public function startKycProcess(string $reference, ?string $verificationUrl = null, ?string $driver = null): void
    {
        $kyc = $this->kyc()->firstOrCreate([
            'kycable_id' => $this->getKey(),
            'kycable_type' => static::class,
        ]);

        $kyc->startKycProcess($reference, $verificationUrl, $driver);
    }

    /**
     * Update user KYC status
     */
    public function updateKycStatus(KycStatusEnum $status, ?array $data = null, ?string $notes = null): void
    {
        $kyc = $this->kyc;

        if ($kyc) {
            $kyc->updateKycStatus($status, $data, $notes);
        }
    }

    /**
     * Update user KYC data without changing status
     */
    public function updateKycData(array $data): void
    {
        $kyc = $this->kyc()->firstOrCreate([
            'kycable_id' => $this->getKey(),
            'kycable_type' => static::class,
        ]);

        $kyc->update([
            'data' => array_merge($kyc->data ?? [], $data),
        ]);
    }

    /**
     * Get KYC reference
     */
    public function getKycReference(): ?string
    {
        $kyc = $this->kyc;

        return $kyc?->reference;
    }

    /**
     * Check if user has KYC verification
     */
    public function hasKyc(): bool
    {
        return $this->kyc !== null;
    }

    /**
     * Get KYC driver used
     */
    public function getKycDriver(): ?string
    {
        $kyc = $this->kyc;

        return $kyc?->driver;
    }
}
