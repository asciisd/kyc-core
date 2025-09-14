<?php

namespace Asciisd\KycCore\Services;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Events\VerificationStarted;
use Asciisd\KycCore\Models\Kyc;
use Illuminate\Database\Eloquent\Model;
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
     * Retrieve verification using the default driver
     */
    public function retrieveVerification(string $reference): KycVerificationResponse
    {
        return $this->driver()->retrieveVerification($reference);
    }

    /**
     * Process webhook using the default driver
     */
    public function processWebhook(array $payload, array $headers = []): KycVerificationResponse
    {
        $driver = $this->driver();
        $response = $driver->processWebhook($payload, $headers);

        // Update status based on webhook response
        $kyc = Kyc::where('reference', $response->reference)->first();
        if ($kyc) {
            $this->statusService->updateStatus($kyc->kycable, $response, $driver);
        }

        return $response;
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
        
        if (!$driverClass || !class_exists($driverClass)) {
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
