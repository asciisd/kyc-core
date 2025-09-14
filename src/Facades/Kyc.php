<?php

namespace Asciisd\KycCore\Facades;

use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static KycVerificationResponse createVerification(Model $user, KycVerificationRequest $request)
 * @method static KycVerificationResponse createSimpleVerification(Model $user, array $options = [])
 * @method static KycVerificationResponse retrieveVerification(string $reference)
 * @method static KycVerificationResponse processWebhook(array $payload, array $headers = [])
 * @method static array downloadDocuments(Model $user, string $reference)
 * @method static array getAvailableDrivers()
 * @method static bool hasDriver(string $driver)
 * @method static bool isDriverEnabled(string $driver)
 * @method static \Asciisd\KycCore\Contracts\KycDriverInterface getDriver(string $driver = null)
 * @method static \Asciisd\KycCore\Contracts\KycDriverInterface driver(string $driver = null)
 */
class Kyc extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'kyc';
    }
}
