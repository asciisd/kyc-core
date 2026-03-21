<?php

namespace Asciisd\KycCore\Services;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Events\VerificationStarted;
use Asciisd\KycCore\Models\Kyc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class KycManager extends Manager
{
    public function __construct(
        private readonly StatusService $statusService,
        private readonly ValidationService $validationService
    ) {
        parent::__construct(app());
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('kyc.default_driver', 'shuftipro');
    }

    /**
     * Create verification using the default driver
     */
    public function createVerification(Model $user, KycVerificationRequest $request): KycVerificationResponse
    {
        $this->validationService->validateUser($user);
        $this->validationService->validateRequest($request);

        $driver = $this->driver();
        $response = $driver->createVerification($user, $request);

        // Update user's KYC status
        $this->statusService->updateStatus($user, $response, $driver);

        // Fire event
        event(new VerificationStarted($user, $response->reference, $driver->getName()));

        return $response;
    }

    /**
     * Create simple verification using the default driver
     */
    public function createSimpleVerification(Model $user, array $options = []): KycVerificationResponse
    {
        $driver = $this->driver();
        $response = $driver->createSimpleVerification($user, $options);

        // Update user's KYC status
        $this->statusService->updateStatus($user, $response, $driver);

        // Fire event
        event(new VerificationStarted($user, $response->reference, $driver->getName()));

        return $response;
    }

    /**
     * Resume existing verification using the default driver
     */
    public function resumeVerification(Model $user): KycVerificationResponse
    {
        $this->validationService->validateUser($user);

        // Get the latest KYC record to ensure we're working with the most recent verification
        $kyc = $user->latestKyc();
        if (! $kyc || ! $kyc->canResumeKyc()) {
            throw new InvalidArgumentException('No resumable verification found for user');
        }

        $driver = $this->driver();

        // First, get the latest status from the provider to ensure we have current information
        try {
            $latestResponse = $driver->retrieveVerification($kyc->reference);

            // Update the local status based on the latest provider response
            $this->statusService->updateStatus($user, $latestResponse, $driver);

            // Refresh the KYC model to get updated status
            $kyc->refresh();

            // Check if the status has changed to a non-resumable state
            if (! $kyc->canResumeKyc()) {
                throw new InvalidArgumentException('Verification status has changed and can no longer be resumed. Current status: '.$kyc->status->label());
            }
        } catch (\Exception $e) {
            // If we can't retrieve the latest status, proceed with caution
            Log::warning('Could not retrieve latest verification status for resume', [
                'reference' => $kyc->reference,
                'error' => $e->getMessage(),
            ]);
        }

        // Check if the driver can resume the verification with current status
        if (! $driver->canResumeVerification($kyc->reference)) {
            throw new InvalidArgumentException('Verification cannot be resumed with the current provider. The verification may have expired or moved to a different state.');
        }

        // Try to get existing verification URL first
        $verificationUrl = $kyc->getActiveVerificationUrl();

        if (! $verificationUrl) {
            // If no active URL, try to get one from the driver
            $verificationUrl = $driver->getVerificationUrl($kyc->reference);
        }

        // For request.pending status, verification URL might not be available yet
        // In this case, we should create a new verification instead of failing
        if (! $verificationUrl && $kyc->status === KycStatusEnum::RequestPending) {
            throw new InvalidArgumentException('Verification is still pending and no URL is available. A new verification should be created.');
        }

        if (! $verificationUrl) {
            throw new InvalidArgumentException('No active verification URL available for resume');
        }

        // Create response with existing data
        $response = new KycVerificationResponse(
            reference: $kyc->reference,
            event: 'verification.resumed',
            success: true,
            verificationUrl: $verificationUrl,
            rawResponse: $kyc->data ?? []
        );

        // Update the verification URL timestamp
        $kyc->updateKycData([
            'verification_url' => $verificationUrl,
            'verification_url_created_at' => now()->toISOString(),
            'resumed_at' => now()->toISOString(),
        ]);

        return $response;
    }

    /**
     * Retrieve verification using the default driver
     */
    public function retrieveVerification(string $reference): KycVerificationResponse
    {
        return $this->driver()->retrieveVerification($reference);
    }

    /**
     * Update user status based on verification response
     */
    public function updateStatusFromResponse(Model $user, KycVerificationResponse $response): void
    {
        $driver = $this->driver();
        $this->statusService->updateStatus($user, $response, $driver);
    }

    /**
     * Import a verification result into an existing KYC record.
     *
     * Used for admin imports, cross-environment data transfer, or manual data recovery.
     * Maps the response event to a status, validates it is importable, builds the data
     * payload, updates the KYC record, and triggers user data population if completed.
     *
     * @param  array<string, mixed>  $metadata  Extra data stored alongside the response (e.g. source environment)
     *
     * @throws InvalidArgumentException When the resolved status is not importable
     */
    public function importVerification(
        Kyc $kyc,
        string $reference,
        KycVerificationResponse $response,
        array $metadata = [],
    ): KycStatusEnum {
        $driver = $this->driver($kyc->getDriver());
        $status = $driver->mapEventToStatus($response->event);

        $importableStatuses = [
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::Completed,
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
        ];

        if (! in_array($status, $importableStatuses)) {
            throw new InvalidArgumentException(
                "Verification is not importable — event: {$response->event}, status: {$status->value}"
            );
        }

        $data = array_filter([
            'extracted_data' => $response->extractedData,
            'verification_results' => $response->verificationResults,
            'raw_response' => $response->rawResponse,
            'last_webhook_event' => $response->event,
            ...$metadata,
        ]);

        if (! $kyc->started_at) {
            $kyc->update(['started_at' => now()->subHour()]);
        }

        $kyc->updateKycStatus(
            status: $status,
            data: $data,
            notes: $metadata['notes'] ?? null,
            reference: $reference,
        );

        if ($status->isCompleted() && $kyc->kycable && method_exists($kyc->kycable, 'populateFromKyc')) {
            $kyc->kycable->populateFromKyc();
        }

        Log::info('KYC verification imported', [
            'kyc_id' => $kyc->id,
            'kycable_id' => $kyc->kycable_id,
            'reference' => $reference,
            'status' => $status->value,
        ]);

        return $status;
    }

    /**
     * Process webhook using the default driver
     */
    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse
    {
        $driver = $this->driver();
        $response = $driver->processWebhook($payload, $headers);

        $kyc = $this->resolveKycFromWebhook($response->reference, $payload);
        if (! $kyc) {
            throw new InvalidArgumentException("KYC record not found for reference: {$response->reference}");
        }

        if (! $kyc->kycable) {
            $user = $this->resolveUserByEmail($kyc->kycable_type, $payload, $kyc->data);

            if ($user) {
                $kyc->update([
                    'kycable_id' => $user->getKey(),
                    'kycable_type' => $user::class,
                ]);
                $kyc->setRelation('kycable', $user);

                Log::info('KYC record re-associated with user via email', [
                    'reference' => $response->reference,
                    'user_id' => $user->getKey(),
                    'previous_kycable_id' => $kyc->getOriginal('kycable_id'),
                ]);
            } else {
                Log::warning('KYC webhook received for orphaned record (kycable not found)', [
                    'reference' => $response->reference,
                    'kycable_id' => $kyc->kycable_id,
                    'kycable_type' => $kyc->kycable_type,
                    'event' => $payload['event'] ?? null,
                ]);

                throw new InvalidArgumentException("Associated user not found for KYC reference: {$response->reference}");
            }
        }

        // Handle special data change events with deep merging
        $event = $payload['event'] ?? null;
        if ($event === 'request.data.changed') {
            $this->handleDataChangedEvent($kyc, $payload, $response);
        } else {
            // Standard webhook processing
            $this->statusService->updateStatus($kyc->kycable, $response, $driver);
        }

        return $response;
    }

    /**
     * Resolve a KYC record from webhook data using multiple fallback strategies:
     *  1. Current reference
     *  2. Archived previous_references
     *  3. Email from payload matched to the kycable model
     */
    private function resolveKycFromWebhook(string $reference, array $payload): ?Kyc
    {
        // 1. Direct reference match
        $kyc = Kyc::where('reference', $reference)->first();
        if ($kyc) {
            return $kyc;
        }

        // 2. Archived previous_references match
        $kyc = Kyc::findByPreviousReference($reference);
        if ($kyc) {
            Log::info('KYC Webhook matched via previous_references', [
                'webhook_reference' => $reference,
                'current_reference' => $kyc->reference,
                'kyc_id' => $kyc->id,
            ]);

            return $kyc;
        }

        // 3. Email-based fallback — match the webhook email to a user, then find their KYC
        $email = $payload['email'] ?? null;
        if ($email) {
            $userClass = $this->config->get('kyc.user_model', 'App\\Models\\User');
            $user = $userClass::where('email', $email)->first();

            if ($user) {
                $kyc = Kyc::where('kycable_id', $user->getKey())
                    ->where('kycable_type', $user::class)
                    ->first();

                if ($kyc) {
                    Log::info('KYC Webhook matched via email fallback', [
                        'webhook_reference' => $reference,
                        'current_reference' => $kyc->reference,
                        'email' => $email,
                        'kyc_id' => $kyc->id,
                    ]);

                    // Archive this orphaned reference so future webhooks resolve directly
                    $previous = $kyc->previous_references ?? [];
                    if (! in_array($reference, $previous, true)) {
                        $previous[] = $reference;
                        $kyc->update(['previous_references' => $previous]);
                    }

                    return $kyc;
                }

                $kyc = Kyc::create([
                    'kycable_id' => $user->getKey(),
                    'kycable_type' => $user::class,
                    'reference' => $reference,
                    'status' => KycStatusEnum::NotStarted,
                    'driver' => $this->getDefaultDriver(),
                ]);
                $kyc->setRelation('kycable', $user);

                Log::info('KYC record created via email fallback', [
                    'webhook_reference' => $reference,
                    'email' => $email,
                    'kyc_id' => $kyc->id,
                    'user_id' => $user->getKey(),
                ]);

                return $kyc;
            }
        }

        return null;
    }

    /**
     * Attempt to resolve the user by email from the webhook payload or stored KYC data.
     */
    private function resolveUserByEmail(?string $kycableType, array $payload, ?array $kycData = null): ?Model
    {
        $kycableType = $kycableType ?: $this->config->get('kyc.user_model', 'App\\Models\\User');
        if (! class_exists($kycableType)) {
            return null;
        }

        $email = $payload['email']
            ?? data_get($kycData, 'email')
            ?? data_get($payload, 'verification_data.email')
            ?? null;

        if (! $email) {
            return null;
        }

        return $kycableType::where('email', $email)->first();
    }

    /**
     * Handle request.data.changed event with deep data merging
     */
    protected function handleDataChangedEvent(Kyc $kyc, array $payload, KycVerificationResponse $response): void
    {
        $verificationData = $payload['verification_data'] ?? [];

        if (empty($verificationData)) {
            Log::warning('No verification_data in request.data.changed event', [
                'reference' => $kyc->reference,
                'payload' => $payload,
            ]);

            return;
        }

        // Get existing KYC data
        $existingData = $kyc->data ?? [];
        if (is_string($existingData)) {
            $existingData = json_decode($existingData, true) ?? [];
        }

        // Deep merge the new verification data with existing data
        $mergedData = $this->deepMergeKycData($existingData, [
            'verification_data' => $verificationData,
            'last_webhook_event' => $payload['event'],
            'last_webhook_at' => now()->toISOString(),
            'data_updated_at' => now()->toISOString(),
        ]);

        // Update KYC record with merged data (preserve existing status)
        $kyc->update([
            'data' => $mergedData,
        ]);

        // Trigger user data population if KYC is completed
        if ($kyc->kycable && method_exists($kyc->kycable, 'populateFromKyc') && $kyc->status->isCompleted()) {
            $kyc->kycable->populateFromKyc();
        }

        Log::info('KYC data updated successfully via request.data.changed', [
            'reference' => $kyc->reference,
            'updated_fields' => array_keys($verificationData),
        ]);
    }

    /**
     * Deep merge KYC data arrays, preserving existing data while updating with new values
     */
    protected function deepMergeKycData(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                // Recursively merge nested arrays
                $existing[$key] = $this->deepMergeKycData($existing[$key], $value);
            } else {
                // Overwrite or add new values
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Download documents using the default driver
     */
    public function downloadDocuments(Model $user, string $reference): array
    {
        return $this->driver()->downloadDocuments($user, $reference);
    }

    /**
     * Get available drivers
     */
    public function getAvailableDrivers(): array
    {
        return $this->config->get('kyc.drivers', []);
    }

    /**
     * Check if a driver is available
     */
    public function hasDriver(string $driver): bool
    {
        return array_key_exists($driver, $this->getAvailableDrivers());
    }

    /**
     * Check if a driver is enabled
     */
    public function isDriverEnabled(string $driver): bool
    {
        $drivers = $this->getAvailableDrivers();

        return isset($drivers[$driver]) && ($drivers[$driver]['enabled'] ?? false);
    }

    /**
     * Get driver instance
     */
    public function getDriver(?string $driver = null): KycDriverInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        if (! $this->hasDriver($driver)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not configured.");
        }

        if (! $this->isDriverEnabled($driver)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not enabled.");
        }

        return $this->driver($driver);
    }

    /**
     * Create ShuftiPro driver instance
     */
    protected function createShuftiproDriver(): KycDriverInterface
    {
        $driverClass = $this->config->get('kyc.drivers.shuftipro.class');

        if (! $driverClass || ! class_exists($driverClass)) {
            throw new InvalidArgumentException('ShuftiPro driver class is not configured or does not exist.');
        }

        return $this->container->make($driverClass);
    }

    /**
     * Create driver instance
     */
    protected function createDriver($driver): KycDriverInterface
    {
        $method = 'create'.ucfirst($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $driverClass = $this->config->get("kyc.drivers.{$driver}.class");

        if (! $driverClass) {
            throw new InvalidArgumentException("Driver class for [{$driver}] is not configured.");
        }

        if (! class_exists($driverClass)) {
            throw new InvalidArgumentException("Driver class [{$driverClass}] does not exist.");
        }

        $instance = $this->container->make($driverClass);

        if (! $instance instanceof KycDriverInterface) {
            throw new InvalidArgumentException("Driver [{$driverClass}] must implement KycDriverInterface.");
        }

        return $instance;
    }
}
