<?php

namespace Asciisd\KycCore\DTOs;

class KycVerificationResponse
{
    public function __construct(
        public readonly string $reference,
        public readonly string $event,
        public readonly bool $success,
        public readonly ?string $verificationUrl = null,
        public readonly ?array $extractedData = null,
        public readonly ?array $verificationResults = null,
        public readonly ?array $documentImages = null,
        public readonly ?string $verificationVideo = null,
        public readonly ?string $verificationReport = null,
        public readonly ?string $imageAccessToken = null,
        public readonly ?string $country = null,
        public readonly ?bool $duplicateDetected = null,
        public readonly ?string $declineReason = null,
        public readonly ?array $rawResponse = null,
        public readonly ?string $message = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isPending(): bool
    {
        return in_array($this->event, ['request.pending', 'verification.pending']);
    }

    public function isCompleted(): bool
    {
        return in_array($this->event, ['verification.completed', 'verification.approved']);
    }

    public function isFailed(): bool
    {
        return in_array($this->event, ['verification.failed', 'verification.declined']);
    }

    public function hasVerificationUrl(): bool
    {
        return ! empty($this->verificationUrl);
    }

    public function hasDocuments(): bool
    {
        return ! empty($this->documentImages);
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'event' => $this->event,
            'success' => $this->success,
            'verification_url' => $this->verificationUrl,
            'extracted_data' => $this->extractedData,
            'verification_results' => $this->verificationResults,
            'document_images' => $this->documentImages,
            'verification_video' => $this->verificationVideo,
            'verification_report' => $this->verificationReport,
            'image_access_token' => $this->imageAccessToken,
            'country' => $this->country,
            'duplicate_detected' => $this->duplicateDetected,
            'decline_reason' => $this->declineReason,
            'raw_response' => $this->rawResponse,
            'message' => $this->message,
        ];
    }
}
