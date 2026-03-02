<?php

declare(strict_types=1);

namespace Asciisd\KycCore\Transformers;

use Asciisd\KycCore\Contracts\KycDataTransformerInterface;
use Asciisd\KycCore\DTOs\StandardizedKycData;

class GenericTransformer implements KycDataTransformerInterface
{
    public function transform(array $rawData): array
    {
        $standardizedData = new StandardizedKycData(
            firstName: $this->extractValue($rawData, ['first_name', 'firstName', 'given_name', 'givenName']),
            middleName: $this->extractValue($rawData, ['middle_name', 'middleName', 'middle_initial', 'middleInitial']),
            lastName: $this->extractValue($rawData, ['last_name', 'lastName', 'family_name', 'familyName', 'surname']),
            dateOfBirth: $this->extractValue($rawData, ['date_of_birth', 'dateOfBirth', 'dob', 'birth_date', 'birthDate']),
            gender: $this->extractValue($rawData, ['gender', 'sex']),
            nationality: $this->extractValue($rawData, ['nationality', 'citizen_country', 'citizenCountry']),
            country: $this->extractValue($rawData, ['country', 'country_code', 'countryCode', 'residence_country', 'residenceCountry']),
            placeOfBirth: $this->extractValue($rawData, ['place_of_birth', 'placeOfBirth', 'birth_place', 'birthPlace']),
            address: $this->extractAddress($rawData),
            city: $this->extractValue($rawData, ['city', 'locality']),
            state: $this->extractValue($rawData, ['state', 'region', 'province', 'administrative_area', 'administrativeArea']),
            postalCode: $this->extractValue($rawData, ['postal_code', 'postalCode', 'zip_code', 'zipCode', 'zip']),
            phoneNumber: $this->extractValue($rawData, ['phone_number', 'phoneNumber', 'phone', 'mobile', 'telephone']),
            email: $this->extractValue($rawData, ['email', 'email_address', 'emailAddress']),
            documents: $this->extractDocuments($rawData),
            additionalData: $this->extractAdditionalData($rawData),
        );

        return $standardizedData->toArray();
    }

    public function canHandle(array $rawData): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'generic';
    }

    private function isValidValue($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value) && strtoupper(trim($value)) === 'N/A') {
            return false;
        }

        return true;
    }

    private function extractValue(array $data, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            $value = data_get($data, $key);
            if ($this->isValidValue($value)) {
                return is_string($value) ? trim($value) : (string) $value;
            }
        }

        return null;
    }

    private function extractAddress(array $rawData): ?string
    {
        $addressFields = ['address', 'full_address', 'fullAddress', 'street_address', 'streetAddress'];

        foreach ($addressFields as $field) {
            $value = data_get($rawData, $field);
            if ($this->isValidValue($value)) {
                return trim($value);
            }
        }

        return null;
    }

    private function extractDocuments(array $rawData): ?array
    {
        if (isset($rawData['documents']) && is_array($rawData['documents'])) {
            return $rawData['documents'];
        }

        $documentInfo = [];
        $documentFields = [
            'document_type' => ['document_type', 'documentType', 'id_type', 'idType'],
            'document_number' => ['document_number', 'documentNumber', 'id_number', 'idNumber'],
            'issue_date' => ['issue_date', 'issueDate', 'issued_date', 'issuedDate'],
            'expiry_date' => ['expiry_date', 'expiryDate', 'expiration_date', 'expirationDate'],
        ];

        foreach ($documentFields as $standardKey => $possibleKeys) {
            $value = $this->extractValue($rawData, $possibleKeys);
            if ($value) {
                $documentInfo[$standardKey] = $value;
            }
        }

        return ! empty($documentInfo) ? $documentInfo : null;
    }

    private function extractAdditionalData(array $rawData): ?array
    {
        $standardFields = [
            'first_name', 'firstName', 'given_name', 'givenName',
            'last_name', 'lastName', 'family_name', 'familyName', 'surname',
            'date_of_birth', 'dateOfBirth', 'dob',
            'gender', 'sex', 'nationality', 'country',
            'address', 'city', 'state', 'region',
            'postal_code', 'postalCode', 'zip_code', 'zipCode',
            'phone_number', 'phoneNumber', 'phone',
            'email', 'documents',
        ];

        $additionalData = [];

        foreach ($rawData as $key => $value) {
            if (! in_array($key, $standardFields) && ! empty($value)) {
                $additionalData[$key] = $value;
            }
        }

        return ! empty($additionalData) ? $additionalData : null;
    }
}
