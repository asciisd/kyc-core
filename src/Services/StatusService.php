<?php

namespace Asciisd\KycCore\Services;

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
    public function updateStatus(Model $user, KycVerificationResponse $response): void
    {
        $kyc = $this->findOrCreateKyc($user);

        $status = $this->mapResponseToStatus($response);
        $data = $this->extractDataFromResponse($response);

        $kyc->updateKycStatus($status, $data, null, $response->reference);

        // Fire appropriate events
        $this->fireStatusEvents($user, $response, $status);

        Log::info('KYC status updated', [
            'user_id' => $user->getKey(),
            'reference' => $response->reference,
            'status' => $status->value,
            'event' => $response->event,
        ]);
    }

    /**
     * Find existing KYC record or create new one
     */
    private function findOrCreateKyc(Model $user): Kyc
    {
        return Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->firstOrCreate([
                'kycable_id' => $user->getKey(),
                'kycable_type' => $user::class,
                'reference' => 'temp_ref_' . uniqid(),
            ]);
    }

    /**
     * Map verification response to KYC status
     */
    private function mapResponseToStatus(KycVerificationResponse $response): KycStatusEnum
    {
        return match ($response->event) {
            'request.pending' => KycStatusEnum::RequestPending,
            'verification.pending' => KycStatusEnum::InProgress,
            'verification.in_progress' => KycStatusEnum::InProgress,
            'verification.review_pending' => KycStatusEnum::ReviewPending,
            'verification.completed' => KycStatusEnum::Completed,
            'verification.approved' => KycStatusEnum::Completed,
            'verification.failed' => KycStatusEnum::VerificationFailed,
            'verification.declined' => KycStatusEnum::Rejected,
            'verification.cancelled' => KycStatusEnum::VerificationCancelled,
            'request.timeout' => KycStatusEnum::RequestTimeout,
            default => KycStatusEnum::InProgress,
        };
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
