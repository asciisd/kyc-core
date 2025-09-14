<?php

namespace Asciisd\KycCore\Contracts;

use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Illuminate\Database\Eloquent\Model;

interface KycDriverInterface
{
    /**
     * Create a new KYC verification session
     */
    public function createVerification(Model $user, KycVerificationRequest $request): KycVerificationResponse;

    /**
     * Create a simple verification with minimal configuration
     */
    public function createSimpleVerification(Model $user, array $options = []): KycVerificationResponse;

    /**
     * Retrieve verification status and data
     */
    public function retrieveVerification(string $reference): KycVerificationResponse;

    /**
     * Check if verification can be resumed
     */
    public function canResumeVerification(string $reference): bool;

    /**
     * Get active verification URL if available
     */
    public function getVerificationUrl(string $reference): ?string;

    /**
     * Process webhook callback from the provider
     */
    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse;

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(array $payload, array $headers): bool;

    /**
     * Download and store verification documents
     */
    public function downloadDocuments(Model $user, string $reference): array;

    /**
     * Get provider-specific configuration
     */
    public function getConfig(): array;

    /**
     * Get driver name
     */
    public function getName(): string;

    /**
     * Check if driver is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get driver capabilities
     */
    public function getCapabilities(): array;

    /**
     * Map provider-specific event to standardized KYC status
     */
    public function mapEventToStatus(string $event): \Asciisd\KycCore\Enums\KycStatusEnum;
}
