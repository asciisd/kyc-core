<?php

namespace Asciisd\KycCore\DTOs;

class KycVerificationRequest
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $country = null,
        public readonly ?string $language = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $callbackUrl = null,
        public readonly ?string $reference = null,
        public readonly ?string $journeyId = null,
        public readonly ?array $allowedCountries = null,
        public readonly ?array $deniedCountries = null,
        public readonly ?array $additionalData = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'country' => $this->country,
            'language' => $this->language,
            'redirect_url' => $this->redirectUrl,
            'callback_url' => $this->callbackUrl,
            'reference' => $this->reference,
            'journey_id' => $this->journeyId,
            'allowed_countries' => $this->allowedCountries,
            'denied_countries' => $this->deniedCountries,
            'additional_data' => $this->additionalData,
        ], fn ($value) => $value !== null);
    }
}
