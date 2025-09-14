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

        $status = $driver->mapEventToStatus($response->event);
        $data = $this->extractDataFromResponse($response);

        $kyc->updateKycStatus($status, $data, null, $response->reference);

        // Fire appropriate events
        $this->fireStatusEvents($user, $response, $status);

        Log::info('KYC status updated', [
            'user_id' => $user->getKey(),
            'reference' => $response->reference,
            'status' => $status->value,
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
            'reference' => $reference ?? 'temp_ref_' . uniqid(),
            'status' => KycStatusEnum::NotStarted,
        ]);
    }


    /**
     * Extract relevant data from verification response
     */
    private function extractDataFromResponse(KycVerificationResponse $response): array
    {
        $data = [];

        if ($response->verificationUrl) {
            $data['verification_url'] = $response->verificationUrl;
            $data['verification_url_created_at'] = now()->toISOString();
        }

        if ($response->extractedData) {
            $data['extracted_data'] = $response->extractedData;
        }

        if ($response->verificationResults) {
            $data['verification_results'] = $response->verificationResults;
        }

        if ($response->documentImages) {
            $data['document_images'] = $response->documentImages;
        }

        if ($response->country) {
            $data['country'] = $response->country;
        }

        if ($response->duplicateDetected !== null) {
            $data['duplicate_detected'] = $response->duplicateDetected;
        }

        if ($response->declineReason) {
            $data['decline_reason'] = $response->declineReason;
        }

        if ($response->message) {
            $data['message'] = $response->message;
        }

        // Store additional ShuftiPro specific data from raw response
        if ($response->rawResponse) {
            if (isset($response->rawResponse['verification_data'])) {
                $data['verification_data'] = $response->rawResponse['verification_data'];
            }
            if (isset($response->rawResponse['verification_result'])) {
                $data['verification_result'] = $response->rawResponse['verification_result'];
            }
            if (isset($response->rawResponse['info'])) {
                $data['info'] = $response->rawResponse['info'];
            }
        }

        $data['last_webhook_event'] = $response->event;
        $data['last_webhook_at'] = now()->toISOString();

        return $data;
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
     * Check if user needs to complete KYC
     */
    public function needsKycVerification(Model $user): bool
    {
        $status = $this->getCurrentStatus($user);

        return $status === null || $status->needsAction();
    }
}
