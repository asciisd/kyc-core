<?php

namespace Asciisd\KycCore\Tests\Unit\Enums;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Tests\TestCase;

class KycStatusEnumTest extends TestCase
{
    public function test_all_status_values()
    {
        $expectedValues = [
            'not_started',
            'request_pending',
            'in_progress',
            'review_pending',
            'verification_completed',
            'verification_failed',
            'verification_cancelled',
            'request_timeout',
            'completed',
            'rejected',
        ];

        $actualValues = array_map(fn ($case) => $case->value, KycStatusEnum::cases());

        $this->assertEquals($expectedValues, $actualValues);
    }

    public function test_all_status_labels()
    {
        $expectedLabels = [
            KycStatusEnum::NotStarted->label() => 'Not Started',
            KycStatusEnum::RequestPending->label() => 'Request Pending',
            KycStatusEnum::InProgress->label() => 'Verification In Progress',
            KycStatusEnum::ReviewPending->label() => 'Review Pending',
            KycStatusEnum::VerificationCompleted->label() => 'Verification Completed',
            KycStatusEnum::VerificationFailed->label() => 'Verification Failed',
            KycStatusEnum::VerificationCancelled->label() => 'Verification Cancelled',
            KycStatusEnum::RequestTimeout->label() => 'Request Timeout',
            KycStatusEnum::Completed->label() => 'KYC Completed',
            KycStatusEnum::Rejected->label() => 'KYC Rejected',
        ];

        foreach ($expectedLabels as $actual => $expected) {
            $this->assertEquals($expected, $actual);
        }
    }

    public function test_can_be_resumed_status()
    {
        $resumableStatuses = [
            KycStatusEnum::InProgress,
            KycStatusEnum::RequestPending,
        ];

        $nonResumableStatuses = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
            KycStatusEnum::Completed,
            KycStatusEnum::Rejected,
        ];

        foreach ($resumableStatuses as $status) {
            $this->assertTrue($status->canBeResumed(), "Status {$status->value} should be resumable");
        }

        foreach ($nonResumableStatuses as $status) {
            $this->assertFalse($status->canBeResumed(), "Status {$status->value} should not be resumable");
        }
    }

    public function test_all_status_descriptions()
    {
        $expectedDescriptions = [
            KycStatusEnum::NotStarted->description() => 'KYC verification has not been started',
            KycStatusEnum::RequestPending->description() => 'KYC verification request is pending',
            KycStatusEnum::InProgress->description() => 'KYC verification is currently in progress',
            KycStatusEnum::ReviewPending->description() => 'KYC verification is pending review',
            KycStatusEnum::VerificationCompleted->description() => 'Identity verification has been completed',
            KycStatusEnum::VerificationFailed->description() => 'Identity verification has failed',
            KycStatusEnum::VerificationCancelled->description() => 'Identity verification was cancelled',
            KycStatusEnum::RequestTimeout->description() => 'KYC verification request has timed out',
            KycStatusEnum::Completed->description() => 'KYC verification process has been completed',
            KycStatusEnum::Rejected->description() => 'KYC verification has been rejected',
        ];

        foreach ($expectedDescriptions as $actual => $expected) {
            $this->assertEquals($expected, $actual);
        }
    }

    public function test_all_status_colors()
    {
        $expectedColors = [
            KycStatusEnum::NotStarted->color() => 'gray',
            KycStatusEnum::RequestPending->color() => 'yellow',
            KycStatusEnum::InProgress->color() => 'blue',
            KycStatusEnum::ReviewPending->color() => 'orange',
            KycStatusEnum::VerificationCompleted->color() => 'green',
            KycStatusEnum::VerificationFailed->color() => 'red',
            KycStatusEnum::VerificationCancelled->color() => 'gray',
            KycStatusEnum::RequestTimeout->color() => 'red',
            KycStatusEnum::Completed->color() => 'green',
            KycStatusEnum::Rejected->color() => 'red',
        ];

        foreach ($expectedColors as $actual => $expected) {
            $this->assertEquals($expected, $actual);
        }
    }

    public function test_needs_action_statuses()
    {
        $statusesThatNeedAction = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
        ];

        $statusesThatDontNeedAction = [
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::Completed,
            KycStatusEnum::Rejected,
        ];

        foreach ($statusesThatNeedAction as $status) {
            $this->assertTrue($status->needsAction(), "Status {$status->value} should need action");
        }

        foreach ($statusesThatDontNeedAction as $status) {
            $this->assertFalse($status->needsAction(), "Status {$status->value} should not need action");
        }
    }

    public function test_can_start_identity_verification_statuses()
    {
        $statusesThatCanStart = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
        ];

        $statusesThatCannotStart = [
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::Completed,
            KycStatusEnum::Rejected,
        ];

        foreach ($statusesThatCanStart as $status) {
            $this->assertTrue($status->canStartIdentityVerification(), "Status {$status->value} should allow starting verification");
        }

        foreach ($statusesThatCannotStart as $status) {
            $this->assertFalse($status->canStartIdentityVerification(), "Status {$status->value} should not allow starting verification");
        }
    }

    public function test_is_in_progress_statuses()
    {
        $statusesInProgress = [
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
        ];

        $statusesNotInProgress = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
            KycStatusEnum::Completed,
            KycStatusEnum::Rejected,
        ];

        foreach ($statusesInProgress as $status) {
            $this->assertTrue($status->isInProgress(), "Status {$status->value} should be in progress");
        }

        foreach ($statusesNotInProgress as $status) {
            $this->assertFalse($status->isInProgress(), "Status {$status->value} should not be in progress");
        }
    }

    public function test_is_completed_statuses()
    {
        $completedStatuses = [
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::Completed,
        ];

        $notCompletedStatuses = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
            KycStatusEnum::Rejected,
        ];

        foreach ($completedStatuses as $status) {
            $this->assertTrue($status->isCompleted(), "Status {$status->value} should be completed");
        }

        foreach ($notCompletedStatuses as $status) {
            $this->assertFalse($status->isCompleted(), "Status {$status->value} should not be completed");
        }
    }

    public function test_is_failed_statuses()
    {
        $failedStatuses = [
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::Rejected,
            KycStatusEnum::RequestTimeout,
        ];

        $notFailedStatuses = [
            KycStatusEnum::NotStarted,
            KycStatusEnum::RequestPending,
            KycStatusEnum::InProgress,
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::Completed,
        ];

        foreach ($failedStatuses as $status) {
            $this->assertTrue($status->isFailed(), "Status {$status->value} should be failed");
        }

        foreach ($notFailedStatuses as $status) {
            $this->assertFalse($status->isFailed(), "Status {$status->value} should not be failed");
        }
    }

    public function test_enum_can_be_serialized_to_json()
    {
        $status = KycStatusEnum::InProgress;
        $json = json_encode($status);

        $this->assertEquals('"in_progress"', $json);
    }

    public function test_enum_can_be_created_from_string()
    {
        $status = KycStatusEnum::from('in_progress');

        $this->assertEquals(KycStatusEnum::InProgress, $status);
    }

    public function test_enum_can_be_created_from_string_case_insensitive()
    {
        $status = KycStatusEnum::tryFromCaseInsensitive('IN_PROGRESS');

        $this->assertEquals(KycStatusEnum::InProgress, $status);
    }

    public function test_enum_returns_null_for_invalid_string()
    {
        $status = KycStatusEnum::tryFrom('invalid_status');

        $this->assertNull($status);
    }

    public function test_needs_kyc_verification_or_resume_statuses()
    {
        $statusesThatNeedVerificationOrResume = [
            // Statuses that need action (fresh start)
            KycStatusEnum::NotStarted,
            KycStatusEnum::VerificationFailed,
            KycStatusEnum::VerificationCancelled,
            KycStatusEnum::RequestTimeout,
            // Statuses that can be resumed
            KycStatusEnum::InProgress,
            KycStatusEnum::RequestPending,
        ];

        $statusesThatDontNeedVerificationOrResume = [
            KycStatusEnum::ReviewPending,
            KycStatusEnum::VerificationCompleted,
            KycStatusEnum::Completed,
            KycStatusEnum::Rejected,
        ];

        foreach ($statusesThatNeedVerificationOrResume as $status) {
            $this->assertTrue($status->needsKycVerificationOrResume(), "Status {$status->value} should need verification or resume");
        }

        foreach ($statusesThatDontNeedVerificationOrResume as $status) {
            $this->assertFalse($status->needsKycVerificationOrResume(), "Status {$status->value} should not need verification or resume");
        }
    }
}
