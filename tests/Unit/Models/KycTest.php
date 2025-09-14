<?php

namespace Asciisd\KycCore\Tests\Unit\Models;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Tests\TestCase;

class KycTest extends TestCase
{
    public function test_kyc_model_fillable_attributes()
    {
        $fillable = [
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

        $kyc = new Kyc;
        $this->assertEquals($fillable, $kyc->getFillable());
    }

    public function test_kyc_model_casts()
    {
        $kyc = new Kyc;
        $casts = $kyc->getCasts();

        $this->assertArrayHasKey('status', $casts);
        $this->assertArrayHasKey('started_at', $casts);
        $this->assertArrayHasKey('completed_at', $casts);
        $this->assertArrayHasKey('data', $casts);

        $this->assertEquals(KycStatusEnum::class, $casts['status']);
        $this->assertEquals('datetime', $casts['started_at']);
        $this->assertEquals('datetime', $casts['completed_at']);
        $this->assertEquals('array', $casts['data']);
    }

    public function test_kyc_model_kycable_relationship()
    {
        $kyc = new Kyc;
        $relationship = $kyc->kycable();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class, $relationship);
    }

    public function test_get_active_verification_url_with_valid_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => [
                'verification_url' => 'https://test.verification.url',
                'verification_url_created_at' => now()->toISOString(),
            ],
        ]);

        $url = $kyc->getActiveVerificationUrl();
        $this->assertEquals('https://test.verification.url', $url);
    }

    public function test_get_active_verification_url_with_expired_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => [
                'verification_url' => 'https://test.verification.url',
                'verification_url_created_at' => now()->subHours(25)->toISOString(), // Expired
            ],
        ]);

        $url = $kyc->getActiveVerificationUrl();
        $this->assertNull($url);
    }

    public function test_get_active_verification_url_without_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => [],
        ]);

        $url = $kyc->getActiveVerificationUrl();
        $this->assertNull($url);
    }

    public function test_can_resume_kyc_with_active_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => [
                'verification_url' => 'https://test.verification.url',
                'verification_url_created_at' => now()->toISOString(),
            ],
        ]);

        $this->assertTrue($kyc->canResumeKyc());
    }

    public function test_can_resume_kyc_with_expired_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => [
                'verification_url' => 'https://test.verification.url',
                'verification_url_created_at' => now()->subHours(25)->toISOString(),
            ],
        ]);

        $this->assertFalse($kyc->canResumeKyc());
    }

    public function test_can_resume_kyc_with_wrong_status()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::Completed,
            'data' => [
                'verification_url' => 'https://test.verification.url',
                'verification_url_created_at' => now()->toISOString(),
            ],
        ]);

        $this->assertFalse($kyc->canResumeKyc());
    }

    public function test_update_kyc_status()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => ['existing' => 'data'],
        ]);

        $kyc->updateKycStatus(KycStatusEnum::Completed, ['new' => 'data'], 'Test notes');

        $kyc->refresh();

        $this->assertEquals(KycStatusEnum::Completed, $kyc->status);
        $this->assertArrayHasKey('existing', $kyc->data);
        $this->assertArrayHasKey('new', $kyc->data);
        $this->assertEquals('data', $kyc->data['existing']);
        $this->assertEquals('data', $kyc->data['new']);
        $this->assertEquals('Test notes', $kyc->notes);
        $this->assertNotNull($kyc->completed_at);
    }

    public function test_update_kyc_status_without_data()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
            'data' => ['existing' => 'data'],
        ]);

        $kyc->updateKycStatus(KycStatusEnum::VerificationFailed);

        $kyc->refresh();

        $this->assertEquals(KycStatusEnum::VerificationFailed, $kyc->status);
        $this->assertEquals(['existing' => 'data'], $kyc->data);
        $this->assertNull($kyc->completed_at);
    }

    public function test_start_kyc_process()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => '',
            'status' => KycStatusEnum::NotStarted,
        ]);

        $kyc->startKycProcess('new_ref_123', 'https://new.verification.url', 'shuftipro');

        $kyc->refresh();

        $this->assertEquals(KycStatusEnum::InProgress, $kyc->status);
        $this->assertEquals('new_ref_123', $kyc->reference);
        $this->assertEquals('shuftipro', $kyc->driver);
        $this->assertNotNull($kyc->started_at);
        $this->assertArrayHasKey('verification_url', $kyc->data);
        $this->assertEquals('https://new.verification.url', $kyc->data['verification_url']);
        $this->assertArrayHasKey('verification_url_created_at', $kyc->data);
    }

    public function test_start_kyc_process_without_verification_url()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => '',
            'status' => KycStatusEnum::NotStarted,
        ]);

        $kyc->startKycProcess('new_ref_123');

        $kyc->refresh();

        $this->assertEquals(KycStatusEnum::InProgress, $kyc->status);
        $this->assertEquals('new_ref_123', $kyc->reference);
        $this->assertNotNull($kyc->started_at);
        $this->assertArrayNotHasKey('verification_url', $kyc->data);
    }

    public function test_get_driver()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'driver' => 'custom_driver',
        ]);

        $this->assertEquals('custom_driver', $kyc->getDriver());
    }

    public function test_get_driver_returns_default_when_not_set()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
        ]);

        $this->assertEquals('test', $kyc->getDriver());
    }

    public function test_uses_driver()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'driver' => 'shuftipro',
        ]);

        $this->assertTrue($kyc->usesDriver('shuftipro'));
        $this->assertFalse($kyc->usesDriver('jumio'));
    }

    public function test_get_driver_config()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'driver' => 'test',
        ]);

        $config = $kyc->getDriverConfig();
        $this->assertIsArray($config);
    }

    public function test_get_driver_capabilities()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'driver' => 'test',
        ]);

        $capabilities = $kyc->getDriverCapabilities();
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('document_verification', $capabilities);
    }

    public function test_driver_supports()
    {
        $kyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'driver' => 'test',
        ]);

        $this->assertTrue($kyc->driverSupports('document_verification'));
        $this->assertFalse($kyc->driverSupports('nonexistent_feature'));
    }

    public function test_is_completed()
    {
        $completedKyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::Completed,
        ]);

        $inProgressKyc = Kyc::create([
            'kycable_id' => 2,
            'kycable_type' => 'User',
            'reference' => 'test_ref_2',
            'status' => KycStatusEnum::InProgress,
        ]);

        $this->assertTrue($completedKyc->isCompleted());
        $this->assertFalse($inProgressKyc->isCompleted());
    }

    public function test_is_failed()
    {
        $failedKyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::VerificationFailed,
        ]);

        $completedKyc = Kyc::create([
            'kycable_id' => 2,
            'kycable_type' => 'User',
            'reference' => 'test_ref_2',
            'status' => KycStatusEnum::Completed,
        ]);

        $this->assertTrue($failedKyc->isFailed());
        $this->assertFalse($completedKyc->isFailed());
    }

    public function test_is_in_progress()
    {
        $inProgressKyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::InProgress,
        ]);

        $completedKyc = Kyc::create([
            'kycable_id' => 2,
            'kycable_type' => 'User',
            'reference' => 'test_ref_2',
            'status' => KycStatusEnum::Completed,
        ]);

        $this->assertTrue($inProgressKyc->isInProgress());
        $this->assertFalse($completedKyc->isInProgress());
    }

    public function test_needs_action()
    {
        $failedKyc = Kyc::create([
            'kycable_id' => 1,
            'kycable_type' => 'User',
            'reference' => 'test_ref',
            'status' => KycStatusEnum::VerificationFailed,
        ]);

        $completedKyc = Kyc::create([
            'kycable_id' => 2,
            'kycable_type' => 'User',
            'reference' => 'test_ref_2',
            'status' => KycStatusEnum::Completed,
        ]);

        $this->assertTrue($failedKyc->needsAction());
        $this->assertFalse($completedKyc->needsAction());
    }
}
