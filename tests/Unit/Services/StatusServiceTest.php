<?php

namespace Asciisd\KycCore\Tests\Unit\Services;

use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\StatusService;
use Asciisd\KycCore\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class StatusServiceTest extends TestCase
{
    private StatusService $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusService = $this->app->make(StatusService::class);
    }

    public function test_update_status_creates_kyc_record()
    {
        Event::fake();

        $user = $this->createTestUser();
        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'request.pending',
            success: true,
            verificationUrl: 'https://test.url'
        );

        $this->statusService->updateStatus($user, $response);

        $kyc = Kyc::where('kycable_id', $user->getKey())
            ->where('kycable_type', $user::class)
            ->first();

        $this->assertNotNull($kyc);
        $this->assertEquals('test_ref_123', $kyc->reference);
        $this->assertEquals(KycStatusEnum::RequestPending, $kyc->status);
        $this->assertArrayHasKey('verification_url', $kyc->data);
        $this->assertEquals('https://test.url', $kyc->data['verification_url']);
    }

    public function test_update_status_updates_existing_kyc_record()
    {
        Event::fake();

        $user = $this->createTestUser();

        // Create initial KYC record
        $kyc = Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => 'test_ref_123',
            'status' => KycStatusEnum::RequestPending,
        ]);

        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'verification.completed',
            success: true,
            extractedData: ['name' => 'John Doe']
        );

        $this->statusService->updateStatus($user, $response);

        $kyc->refresh();
        $this->assertEquals(KycStatusEnum::Completed, $kyc->status);
        $this->assertArrayHasKey('extracted_data', $kyc->data);
        $this->assertEquals(['name' => 'John Doe'], $kyc->data['extracted_data']);
    }

    public function test_map_response_to_status()
    {
        $testCases = [
            ['request.pending', KycStatusEnum::RequestPending],
            ['verification.pending', KycStatusEnum::InProgress],
            ['verification.in_progress', KycStatusEnum::InProgress],
            ['verification.review_pending', KycStatusEnum::ReviewPending],
            ['verification.completed', KycStatusEnum::VerificationCompleted],
            ['verification.approved', KycStatusEnum::Completed],
            ['verification.failed', KycStatusEnum::VerificationFailed],
            ['verification.declined', KycStatusEnum::Rejected],
            ['verification.cancelled', KycStatusEnum::VerificationCancelled],
            ['request.timeout', KycStatusEnum::RequestTimeout],
            ['unknown.event', KycStatusEnum::InProgress],
        ];

        foreach ($testCases as [$event, $expectedStatus]) {
            $user = $this->createTestUser();
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );

            $this->statusService->updateStatus($user, $response);

            $kyc = Kyc::where('kycable_id', $user->getKey())->first();
            $this->assertEquals($expectedStatus, $kyc->status, "Failed for event: {$event}");
        }
    }

    public function test_extract_data_from_response()
    {
        $user = $this->createTestUser();
        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'verification.completed',
            success: true,
            verificationUrl: 'https://test.url',
            extractedData: ['name' => 'John Doe', 'dob' => '1990-01-01'],
            verificationResults: ['document' => 'verified', 'face' => 'verified'],
            documentImages: ['front.jpg', 'back.jpg'],
            country: 'US',
            duplicateDetected: false,
            declineReason: null,
            message: 'Verification completed successfully'
        );

        $this->statusService->updateStatus($user, $response);

        $kyc = Kyc::where('kycable_id', $user->getKey())->first();
        $data = $kyc->data;

        $this->assertArrayHasKey('verification_url', $data);
        $this->assertArrayHasKey('extracted_data', $data);
        $this->assertArrayHasKey('verification_results', $data);
        $this->assertArrayHasKey('document_images', $data);
        $this->assertArrayHasKey('country', $data);
        $this->assertArrayHasKey('duplicate_detected', $data);
        $this->assertArrayHasKey('decline_reason', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('last_webhook_event', $data);
        $this->assertArrayHasKey('last_webhook_at', $data);
    }

    public function test_fire_status_events()
    {
        Event::fake();

        $user = $this->createTestUser();

        // Test completed event
        $completedResponse = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true
        );

        $this->statusService->updateStatus($user, $completedResponse);

        Event::assertDispatched(\Asciisd\KycCore\Events\VerificationCompleted::class);

        // Test failed event
        $failedResponse = new KycVerificationResponse(
            reference: 'test_ref_2',
            event: 'verification.failed',
            success: false
        );

        $this->statusService->updateStatus($user, $failedResponse);

        Event::assertDispatched(\Asciisd\KycCore\Events\VerificationFailed::class);
    }

    public function test_get_current_status()
    {
        $user = $this->createTestUser();

        // No KYC record
        $status = $this->statusService->getCurrentStatus($user);
        $this->assertNull($status);

        // Create KYC record
        Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
        ]);

        $status = $this->statusService->getCurrentStatus($user);
        $this->assertEquals(KycStatusEnum::InProgress, $status);
    }

    public function test_can_start_kyc()
    {
        $user = $this->createTestUser();

        // No KYC record - can start
        $this->assertTrue($this->statusService->canStartKyc($user));

        // Create KYC record with status that allows starting
        Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => 'test_ref',
            'status' => KycStatusEnum::VerificationFailed,
        ]);

        $this->assertTrue($this->statusService->canStartKyc($user));

        // Update to status that doesn't allow starting
        $kyc = Kyc::where('kycable_id', $user->getKey())->first();
        $kyc->update(['status' => KycStatusEnum::InProgress]);

        $this->assertFalse($this->statusService->canStartKyc($user));
    }

    public function test_has_completed_kyc()
    {
        $user = $this->createTestUser();

        // No KYC record
        $this->assertFalse($this->statusService->hasCompletedKyc($user));

        // Create completed KYC record
        Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => 'test_ref',
            'status' => KycStatusEnum::Completed,
        ]);

        $this->assertTrue($this->statusService->hasCompletedKyc($user));
    }

    public function test_needs_kyc_verification()
    {
        $user = $this->createTestUser();

        // No KYC record - needs verification
        $this->assertTrue($this->statusService->needsKycVerification($user));

        // Create KYC record that needs action
        Kyc::create([
            'kycable_id' => $user->getKey(),
            'kycable_type' => $user::class,
            'reference' => 'test_ref',
            'status' => KycStatusEnum::VerificationFailed,
        ]);

        $this->assertTrue($this->statusService->needsKycVerification($user));

        // Update to completed status
        $kyc = Kyc::where('kycable_id', $user->getKey())->first();
        $kyc->update(['status' => KycStatusEnum::Completed]);

        $this->assertFalse($this->statusService->needsKycVerification($user));
    }

    private function createTestUser(): Model
    {
        return new class extends Model
        {
            protected $fillable = ['email'];

            protected $table = 'users';

            public function getKey()
            {
                return 1;
            }

            public function getMorphClass()
            {
                return 'User';
            }
        };
    }
}
