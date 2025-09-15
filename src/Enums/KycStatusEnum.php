<?php

namespace Asciisd\KycCore\Enums;

enum KycStatusEnum: string
{
    case NotStarted = 'not_started';
    case RequestPending = 'request_pending';
    case InProgress = 'in_progress';
    case ReviewPending = 'review_pending';
    case VerificationCompleted = 'verification_completed';
    case VerificationFailed = 'verification_failed';
    case VerificationCancelled = 'verification_cancelled';
    case RequestTimeout = 'request_timeout';
    case Completed = 'completed';
    case Rejected = 'rejected';

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::RequestPending => 'Request Pending',
            self::InProgress => 'Verification In Progress',
            self::ReviewPending => 'Review Pending',
            self::VerificationCompleted => 'Verification Completed',
            self::VerificationFailed => 'Verification Failed',
            self::VerificationCancelled => 'Verification Cancelled',
            self::RequestTimeout => 'Request Timeout',
            self::Completed => 'KYC Completed',
            self::Rejected => 'KYC Rejected',
        };
    }

    /**
     * Get description for the status
     */
    public function description(): string
    {
        return match ($this) {
            self::NotStarted => 'KYC verification has not been started',
            self::RequestPending => 'KYC verification request is pending',
            self::InProgress => 'KYC verification is currently in progress',
            self::ReviewPending => 'KYC verification is pending review',
            self::VerificationCompleted => 'Identity verification has been completed',
            self::VerificationFailed => 'Identity verification has failed',
            self::VerificationCancelled => 'Identity verification was cancelled',
            self::RequestTimeout => 'KYC verification request has timed out',
            self::Completed => 'KYC verification process has been completed',
            self::Rejected => 'KYC verification has been rejected',
        };
    }

    /**
     * Get color for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::RequestPending => 'yellow',
            self::InProgress => 'blue',
            self::ReviewPending => 'orange',
            self::VerificationCompleted => 'green',
            self::VerificationFailed => 'red',
            self::VerificationCancelled => 'gray',
            self::RequestTimeout => 'red',
            self::Completed => 'green',
            self::Rejected => 'red',
        };
    }

    /**
     * Check if this status needs user action
     */
    public function needsAction(): bool
    {
        return in_array($this, [
            self::NotStarted,
            self::VerificationFailed,
            self::VerificationCancelled,
            self::RequestTimeout,
        ]);
    }

    /**
     * Check if user can start identity verification
     */
    public function canStartIdentityVerification(): bool
    {
        return in_array($this, [
            self::NotStarted,
            self::VerificationFailed,
            self::VerificationCancelled,
            self::RequestTimeout,
        ]);
    }

    /**
     * Check if verification can be resumed
     */
    public function canBeResumed(): bool
    {
        return in_array($this, [
            self::InProgress,
            self::RequestPending,
        ]);
    }

    /**
     * Check if this status needs KYC verification (either start or resume)
     */
    public function needsKycVerificationOrResume(): bool
    {
        return $this->needsAction() || $this->canBeResumed();
    }

    /**
     * Check if verification is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::RequestPending,
            self::InProgress,
            self::ReviewPending,
        ]);
    }

    /**
     * Check if verification is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this, [
            self::VerificationCompleted,
            self::Completed,
        ]);
    }

    /**
     * Check if verification has failed
     */
    public function isFailed(): bool
    {
        return in_array($this, [
            self::VerificationFailed,
            self::Rejected,
            self::RequestTimeout,
        ]);
    }

    /**
     * Try to create enum from string (case insensitive)
     */
    public static function tryFromCaseInsensitive(string $value): ?self
    {
        $value = strtolower($value);
        
        foreach (self::cases() as $case) {
            if (strtolower($case->value) === $value) {
                return $case;
            }
        }
        
        return null;
    }
}
