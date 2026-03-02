<?php

declare(strict_types=1);

namespace Asciisd\KycCore\DTOs;

readonly class StandardizedKycData
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $middleName = null,
        public ?string $lastName = null,
        public ?string $dateOfBirth = null,
        public ?string $gender = null,
        public ?string $nationality = null,
        public ?string $country = null,
        public ?string $placeOfBirth = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $phoneNumber = null,
        public ?string $email = null,
        public ?array $documents = null,
        public ?array $additionalData = null,
    ) {}

    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'country' => $this->country,
            'place_of_birth' => $this->placeOfBirth,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'phone_number' => $this->phoneNumber,
            'email' => $this->email,
            'documents' => $this->documents,
            'additional_data' => $this->additionalData,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? null,
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            gender: $data['gender'] ?? null,
            nationality: $data['nationality'] ?? null,
            country: $data['country'] ?? null,
            placeOfBirth: $data['place_of_birth'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            email: $data['email'] ?? null,
            documents: $data['documents'] ?? null,
            additionalData: $data['additional_data'] ?? null,
        );
    }

    public function hasData(): bool
    {
        $properties = [
            $this->firstName,
            $this->lastName,
            $this->dateOfBirth,
            $this->gender,
            $this->nationality,
            $this->country,
            $this->address,
        ];

        return collect($properties)->filter()->isNotEmpty();
    }
}
