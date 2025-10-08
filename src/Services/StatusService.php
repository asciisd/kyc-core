<?php

namespace Asciisd\KycCore\Services;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Events\VerificationCompleted;
use Asciisd\KycCore\Events\VerificationFailed;
use Asciisd\KycCore\Models\Kyc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StatusService
{
    /**
     * Update KYC status based on verification response
     */
    public function updateStatus(Model $user, KycVerificationResponse $response, KycDriverInterface $driver): void
    {
        $kyc = $this->findOrCreateKyc($user, $response->reference);

        // Capture the previous status before updating
        $previousStatus = $kyc->status;

        $status = $driver->mapEventToStatus($response->event);
        $data = $this->extractDataFromResponse($response);

        $kyc->updateKycStatus($status, $data, null, $response->reference);

        // Fire appropriate events only if status changed
        $statusChanged = $previousStatus !== $status;
        if ($statusChanged) {
            $this->fireStatusEvents($user, $response, $status);
        }

        Log::info('KYC status updated', [
            'user_id' => $user->getKey(),
            'reference' => $response->reference,
            'previous_status' => $previousStatus->value,
            'new_status' => $status->value,
            'status_changed' => $statusChanged,
            'event' => $response->event,
            'driver' => $driver->getName(),
        ]);
    }

    /**
     * Find existing KYC record or create new one
     */
    private function findOrCreateKyc(Model $user, ?string $reference = null): Kyc
    {
        // First try to find by reference if provided
        if ($reference) {
            $kyc = Kyc::where('reference', $reference)->first();
            if ($kyc) {
                return $kyc;
            }
        }

        // Then try to find existing KYC for user
        $kyc = Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->first();

        if ($kyc) {
            return $kyc;
        }

        // Create new KYC record
        return Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => $reference ?? 'temp_ref_'.uniqid(),
            'status' => KycStatusEnum::NotStarted,
        ]);
    }

    /**
     * Extract relevant data from verification response
     */
    private function extractDataFromResponse(KycVerificationResponse $response): array
    {
        // Store the complete response data directly with webhook metadata
        return array_merge($response->toArray(), [
            'last_webhook_event' => $response->event,
            'last_webhook_at' => now()->toISOString(),
        ]);
    }

    /**
     * Fire appropriate events based on status
     */
    private function fireStatusEvents(Model $user, KycVerificationResponse $response, KycStatusEnum $status): void
    {
        if ($status->isCompleted()) {
            event(new VerificationCompleted($user, $response->reference, $response));
        } elseif ($status->isFailed()) {
            event(new VerificationFailed($user, $response->reference, $response));
        }
    }

    /**
     * Get current KYC status for user
     */
    public function getCurrentStatus(Model $user): ?KycStatusEnum
    {
        $kyc = Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->first();

        return $kyc?->status;
    }

    /**
     * Check if user can start KYC
     */
    public function canStartKyc(Model $user): bool
    {
        $status = $this->getCurrentStatus($user);

        return $status === null || $status->canStartIdentityVerification();
    }

    /**
     * Check if user has completed KYC
     */
    public function hasCompletedKyc(Model $user): bool
    {
        $status = $this->getCurrentStatus($user);

        return $status?->isCompleted() ?? false;
    }

    /**
     * Check if user needs to complete KYC verification (includes both start and resume scenarios)
     */
    public function needsKycVerification(Model $user): bool
    {
        $status = $this->getCurrentStatus($user);

        return $status === null || $status->needsKycVerificationOrResume();
    }
}
