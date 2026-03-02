<?php

declare(strict_types=1);

namespace Asciisd\KycCore\Services;

use Asciisd\KycCore\Contracts\KycDataTransformerInterface;
use Asciisd\KycCore\Transformers\GenericTransformer;

class KycTransformerFactory
{
    /**
     * Available transformers in order of preference.
     *
     * @var KycDataTransformerInterface[]
     */
    private array $transformers = [];

    public function __construct()
    {
        $this->discoverTransformers();
        $this->transformers[] = new GenericTransformer;
    }

    /**
     * Register a transformer.
     */
    public function register(KycDataTransformerInterface $transformer): void
    {
        array_unshift($this->transformers, $transformer);
    }

    /**
     * Get the appropriate transformer for the given data.
     */
    public function getTransformer(array $rawData): KycDataTransformerInterface
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->canHandle($rawData)) {
                return $transformer;
            }
        }

        return new GenericTransformer;
    }

    /**
     * Get transformer by provider name.
     */
    public function getTransformerByProvider(string $providerName): ?KycDataTransformerInterface
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->getProviderName() === strtolower($providerName)) {
                return $transformer;
            }
        }

        return null;
    }

    /**
     * Transform raw KYC data using the appropriate transformer.
     */
    public function transform(array $rawData, ?string $providerHint = null): array
    {
        if ($providerHint) {
            $transformer = $this->getTransformerByProvider($providerHint);
            if ($transformer && $transformer->canHandle($rawData)) {
                return $transformer->transform($rawData);
            }
        }

        $transformer = $this->getTransformer($rawData);

        return $transformer->transform($rawData);
    }

    /**
     * Get all available transformers.
     *
     * @return KycDataTransformerInterface[]
     */
    public function getAllTransformers(): array
    {
        return $this->transformers;
    }

    /**
     * Get all available provider names.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        return array_map(
            fn (KycDataTransformerInterface $transformer) => $transformer->getProviderName(),
            $this->transformers
        );
    }

    /**
     * Discover transformers from registered service providers via config.
     */
    private function discoverTransformers(): void
    {
        $transformerClasses = config('kyc.transformers', []);

        foreach ($transformerClasses as $class) {
            if (class_exists($class)) {
                $this->transformers[] = new $class;
            }
        }
    }
}
