<?php

declare(strict_types=1);

namespace Asciisd\KycCore\Contracts;

interface KycDataTransformerInterface
{
    /**
     * Transform raw KYC data into a standardized format.
     */
    public function transform(array $rawData): array;

    /**
     * Check if this transformer can handle the given data structure.
     */
    public function canHandle(array $rawData): bool;

    /**
     * Get the provider name this transformer handles.
     */
    public function getProviderName(): string;
}
