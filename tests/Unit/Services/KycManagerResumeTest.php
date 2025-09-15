<?php

namespace Asciisd\KycCore\Tests\Unit\Services;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\KycManager;
use Asciisd\KycCore\Services\StatusService;
use Asciisd\KycCore\Services\ValidationService;
use Asciisd\KycCore\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class KycManagerResumeTest extends TestCase
{
    private KycManager $kycManager;
    private KycDriverInterface $mockDriver;
    private StatusService $mockStatusService;
    private ValidationService $mockValidationService;
    private Model $mockUser;
    private Kyc $mockKyc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDriver = Mockery::mock(KycDriverInterface::class);
        $this->mockStatusService = Mockery::mock(StatusService::class);
        $this->mockValidationService = Mockery::mock(ValidationService::class);
        $this->mockUser = Mockery::mock(Model::class);
        $this->mockKyc = Mockery::mock(Kyc::class);

        $this->kycManager = new KycManager($this->mockStatusService, $this->mockValidationService);
        
        // Mock the driver method to return our mock driver
        $this->kycManager = Mockery::mock(KycManager::class, [$this->mockStatusService, $this->mockValidationService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        
        $this->kycManager->shouldReceive('driver')->andReturn($this->mockDriver);
    }

    public function test_can_resume_verification_with_active_url()
    {
        $reference = 'test-ref-123';
        $verificationUrl = 'https://example.com/verify';
        
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn($this->mockKyc);

        $this->mockKyc
            ->shouldReceive('canResumeKyc')
            ->once()
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('reference')
            ->andReturn($reference);

        $this->mockDriver
            ->shouldReceive('canResumeVerification')
            ->once()
            ->with($reference)
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getActiveVerificationUrl')
            ->once()
            ->andReturn($verificationUrl);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('data')
            ->andReturn([]);

        $this->mockKyc
            ->shouldReceive('updateKycData')
            ->once()
            ->with(Mockery::type('array'));

        $response = $this->kycManager->resumeVerification($this->mockUser);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertEquals($reference, $response->reference);
        $this->assertEquals($verificationUrl, $response->verificationUrl);
        $this->assertEquals('verification.resumed', $response->event);
    }

    public function test_can_resume_verification_with_driver_url()
    {
        $reference = 'test-ref-123';
        $verificationUrl = 'https://example.com/verify';
        
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn($this->mockKyc);

        $this->mockKyc
            ->shouldReceive('canResumeKyc')
            ->once()
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('reference')
            ->andReturn($reference);

        $this->mockDriver
            ->shouldReceive('canResumeVerification')
            ->once()
            ->with($reference)
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getActiveVerificationUrl')
            ->once()
            ->andReturn(null);

        $this->mockDriver
            ->shouldReceive('getVerificationUrl')
            ->once()
            ->with($reference)
            ->andReturn($verificationUrl);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('data')
            ->andReturn([]);

        $this->mockKyc
            ->shouldReceive('updateKycData')
            ->once()
            ->with(Mockery::type('array'));

        $response = $this->kycManager->resumeVerification($this->mockUser);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertEquals($reference, $response->reference);
        $this->assertEquals($verificationUrl, $response->verificationUrl);
    }

    public function test_cannot_resume_verification_when_not_resumable()
    {
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn($this->mockKyc);

        $this->mockKyc
            ->shouldReceive('canResumeKyc')
            ->once()
            ->andReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No resumable verification found for user');

        $this->kycManager->resumeVerification($this->mockUser);
    }

    public function test_cannot_resume_verification_when_driver_cannot_resume()
    {
        $reference = 'test-ref-123';
        
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn($this->mockKyc);

        $this->mockKyc
            ->shouldReceive('canResumeKyc')
            ->once()
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('reference')
            ->andReturn($reference);

        $this->mockDriver
            ->shouldReceive('canResumeVerification')
            ->once()
            ->with($reference)
            ->andReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Verification cannot be resumed with the current provider');

        $this->kycManager->resumeVerification($this->mockUser);
    }

    public function test_cannot_resume_verification_when_no_url_available()
    {
        $reference = 'test-ref-123';
        
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn($this->mockKyc);

        $this->mockKyc
            ->shouldReceive('canResumeKyc')
            ->once()
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getAttribute')
            ->with('reference')
            ->andReturn($reference);

        $this->mockDriver
            ->shouldReceive('canResumeVerification')
            ->once()
            ->with($reference)
            ->andReturn(true);

        $this->mockKyc
            ->shouldReceive('getActiveVerificationUrl')
            ->once()
            ->andReturn(null);

        $this->mockDriver
            ->shouldReceive('getVerificationUrl')
            ->once()
            ->with($reference)
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No active verification URL available for resume');

        $this->kycManager->resumeVerification($this->mockUser);
    }

    public function test_cannot_resume_verification_when_no_kyc_exists()
    {
        $this->mockValidationService
            ->shouldReceive('validateUser')
            ->once()
            ->with($this->mockUser);

        $this->mockUser
            ->shouldReceive('getAttribute')
            ->with('kyc')
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No resumable verification found for user');

        $this->kycManager->resumeVerification($this->mockUser);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
