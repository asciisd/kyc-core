<?php

namespace Asciisd\KycCore\Models;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Kyc extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'kycable_id',
        'kycable_type',
        'driver',
        'status',
        'reference',
        'started_at',
        'completed_at',
        'data',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => KycStatusEnum::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'data' => 'array',
        ];
    }

    /**
     * Get the parent kycable model (user, customer, etc.).
     */
    public function kycable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get stored verification URL if still valid
     */
    public function getActiveVerificationUrl(): ?string
    {
        if (! $this->data || ! isset($this->data['verification_url'])) {
            return null;
        }

        // Check if URL was created within the configured expiry time
        if (isset($this->data['verification_url_created_at'])) {
            $createdAt = \Carbon\Carbon::parse($this->data['verification_url_created_at']);
            $expiryHours = config('kyc.settings.verification_url_expiry_hours', 24);

            if ($createdAt->diffInHours(now()) > $expiryHours) {
                return null; // URL expired
            }
        }

        return $this->data['verification_url'];
    }

    /**
     * Check if user can resume existing KYC verification
     */
    public function canResumeKyc(): bool
    {
        return $this->status->canBeResumed() &&
               ($this->getActiveVerificationUrl() !== null || $this->reference !== null);
    }

    /**
     * Update KYC status based on verification result
     */
    public function updateKycStatus(KycStatusEnum $status, ?array $data = null, ?string $notes = null, ?string $reference = null): void
    {
        $updateData = ['status' => $status];

        if ($data) {
            $updateData['data'] = array_merge($this->data ?? [], $data);
        }

        if ($notes) {
            $updateData['notes'] = $notes;
        }

        if ($reference) {
            $updateData['reference'] = $reference;
        }

        // Set started_at when verification begins (first time moving from NotStarted)
        if ($this->status === KycStatusEnum::NotStarted && $status->isInProgress() && !$this->started_at) {
            $updateData['started_at'] = now();
        }

        // Set completed_at when verification is completed
        if ($status->isCompleted()) {
            $updateData['completed_at'] = now();
        }

        $this->update($updateData);
    }

    /**
     * Start KYC process
     */
    public function startKycProcess(string $reference, ?string $verificationUrl = null, ?string $driver = null): void
    {
        $kycData = $this->data ?? [];

        if ($verificationUrl) {
            $kycData['verification_url'] = $verificationUrl;
            $kycData['verification_url_created_at'] = now()->toISOString();
        }

        $updateData = [
            'status' => KycStatusEnum::InProgress,
            'reference' => $reference,
            'started_at' => now(),
            'data' => $kycData,
        ];

        // Set driver if provided, otherwise use default
        if ($driver) {
            $updateData['driver'] = $driver;
        } elseif (! $this->driver) {
            $updateData['driver'] = config('kyc.default_driver', 'shuftipro');
        }

        $this->update($updateData);
    }

    /**
     * Update KYC data without changing status
     */
    public function updateKycData(array $data): void
    {
        $this->update([
            'data' => array_merge($this->data ?? [], $data),
        ]);
    }

    /**
     * Get the KYC driver used for this verification
     */
    public function getDriver(): string
    {
        return $this->driver ?? config('kyc.default_driver', 'shuftipro');
    }

    /**
     * Check if this verification uses a specific driver
     */
    public function usesDriver(string $driver): bool
    {
        return $this->getDriver() === $driver;
    }

    /**
     * Get driver-specific configuration
     */
    public function getDriverConfig(): array
    {
        $driver = $this->getDriver();

        return config("kyc.drivers.{$driver}.config", []);
    }

    /**
     * Get driver capabilities
     */
    public function getDriverCapabilities(): array
    {
        $driver = $this->getDriver();

        return config("kyc.drivers.{$driver}.supports", []);
    }

    /**
     * Check if driver supports a specific feature
     */
    public function driverSupports(string $feature): bool
    {
        $capabilities = $this->getDriverCapabilities();

        return $capabilities[$feature] ?? false;
    }

    /**
     * Check if KYC is completed
     */
    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    /**
     * Check if KYC has failed
     */
    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    /**
     * Check if KYC is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    /**
     * Check if KYC needs action
     */
    public function needsAction(): bool
    {
        return $this->status->needsAction();
    }
}
