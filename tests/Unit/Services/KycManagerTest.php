<?php

namespace Asciisd\KycCore\Tests\Unit\Services;

use Asciisd\KycCore\Contracts\KycDriverInterface;
use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Events\VerificationStarted;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\KycManager;
use Asciisd\KycCore\Services\StatusService;
use Asciisd\KycCore\Services\ValidationService;
use Asciisd\KycCore\Tests\TestCase;
use Asciisd\KycCore\Tests\TestUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class KycManagerTest extends TestCase
{
    private KycManager $kycManager;

    private StatusService $statusService;

    private ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusService = $this->app->make(StatusService::class);
        $this->validationService = $this->app->make(ValidationService::class);
        $this->kycManager = $this->app->make(KycManager::class);
    }

    public function test_get_default_driver()
    {
        $this->assertEquals('test', $this->kycManager->getDefaultDriver());
    }

    public function test_get_available_drivers()
    {
        $drivers = $this->kycManager->getAvailableDrivers();

        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('test', $drivers);
    }

    public function test_has_driver()
    {
        $this->assertTrue($this->kycManager->hasDriver('test'));
        $this->assertFalse($this->kycManager->hasDriver('nonexistent'));
    }

    public function test_is_driver_enabled()
    {
        $this->assertTrue($this->kycManager->isDriverEnabled('test'));
    }

    public function test_get_driver_instance()
    {
        $driver = $this->kycManager->getDriver('test');

        $this->assertInstanceOf(KycDriverInterface::class, $driver);
        $this->assertEquals('test', $driver->getName());
    }

    public function test_get_driver_throws_exception_for_nonexistent_driver()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [nonexistent] is not configured.');

        $this->kycManager->getDriver('nonexistent');
    }

    public function test_create_verification()
    {
        Event::fake();

        $user = $this->createTestUser();
        $request = new KycVerificationRequest(
            email: 'test@example.com',
            country: 'US'
        );

        $response = $this->kycManager->createVerification($user, $request);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertStringStartsWith('test_ref_', $response->reference);

        Event::assertDispatched(VerificationStarted::class);
    }

    public function test_create_simple_verification()
    {
        Event::fake();

        $user = $this->createTestUser();
        $options = ['country' => 'US'];

        $response = $this->kycManager->createSimpleVerification($user, $options);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        Event::assertDispatched(VerificationStarted::class);
    }

    public function test_retrieve_verification()
    {
        $reference = 'test_reference';
        $response = $this->kycManager->retrieveVerification($reference);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertEquals($reference, $response->reference);
    }

    public function test_process_webhook()
    {
        $user = TestUser::create(['email' => 'test@example.com']);

        Kyc::create([
            'kycable_id' => $user->id,
            'kycable_type' => TestUser::class,
            'reference' => 'test_reference',
            'status' => KycStatusEnum::InProgress,
        ]);

        $payload = [
            'reference' => 'test_reference',
            'event' => 'verification.completed',
            'result' => [
                'event' => 'verification.completed',
            ],
        ];
        $headers = [];

        $response = $this->kycManager->processWebhook($payload, $headers);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertEquals('test_reference', $response->reference);
    }

    public function test_download_documents()
    {
        $user = $this->createTestUser();
        $reference = 'test_reference';

        $documents = $this->kycManager->downloadDocuments($user, $reference);

        $this->assertIsArray($documents);
        $this->assertCount(2, $documents);
        $this->assertContains('document1.jpg', $documents);
    }

    public function test_process_webhook_resolves_via_previous_references()
    {
        $user = TestUser::create(['email' => 'test@example.com']);

        Kyc::create([
            'kycable_id' => $user->id,
            'kycable_type' => TestUser::class,
            'reference' => 'ref_current',
            'status' => KycStatusEnum::InProgress,
            'previous_references' => ['ref_old_1', 'ref_old_2'],
        ]);

        $payload = [
            'reference' => 'ref_old_1',
            'event' => 'verification.completed',
        ];

        $response = $this->kycManager->processWebhook($payload, []);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertEquals('ref_old_1', $response->reference);
    }

    public function test_process_webhook_throws_when_no_match()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('KYC record not found for reference');

        $payload = [
            'reference' => 'totally_unknown_ref',
            'event' => 'verification.completed',
        ];

        $this->kycManager->processWebhook($payload, []);
    }

    public function test_import_verification_updates_kyc_record()
    {
        $user = TestUser::create(['email' => 'import@example.com']);

        $kyc = Kyc::create([
            'kycable_id' => $user->id,
            'kycable_type' => TestUser::class,
            'reference' => 'old_ref',
            'status' => KycStatusEnum::InProgress,
        ]);

        $response = new KycVerificationResponse(
            reference: 'imported_ref',
            event: 'verification.completed',
            success: true,
            extractedData: ['first_name' => 'John'],
            rawResponse: ['event' => 'verification.completed'],
        );

        $status = $this->kycManager->importVerification(
            kyc: $kyc,
            reference: 'imported_ref',
            response: $response,
            metadata: [
                'imported_from_environment' => 'production',
                'notes' => 'Test import',
            ],
        );

        $kyc->refresh();

        $this->assertEquals(KycStatusEnum::Completed, $status);
        $this->assertEquals('imported_ref', $kyc->reference);
        $this->assertEquals(['old_ref'], $kyc->previous_references);
        $this->assertArrayHasKey('extracted_data', $kyc->data);
        $this->assertArrayHasKey('imported_from_environment', $kyc->data);
        $this->assertEquals('Test import', $kyc->notes);
        $this->assertNotNull($kyc->completed_at);
    }

    public function test_import_verification_rejects_non_importable_status()
    {
        $user = TestUser::create(['email' => 'fail@example.com']);

        $kyc = Kyc::create([
            'kycable_id' => $user->id,
            'kycable_type' => TestUser::class,
            'reference' => 'some_ref',
            'status' => KycStatusEnum::InProgress,
        ]);

        $response = new KycVerificationResponse(
            reference: 'failed_ref',
            event: 'verification.failed',
            success: false,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not importable');

        $this->kycManager->importVerification($kyc, 'failed_ref', $response);
    }

    public function test_import_verification_sets_started_at_when_null()
    {
        $user = TestUser::create(['email' => 'new@example.com']);

        $kyc = Kyc::create([
            'kycable_id' => $user->id,
            'kycable_type' => TestUser::class,
            'reference' => 'pending_ref',
            'status' => KycStatusEnum::NotStarted,
        ]);

        $this->assertNull($kyc->started_at);

        $response = new KycVerificationResponse(
            reference: 'import_ref',
            event: 'verification.completed',
            success: true,
        );

        $this->kycManager->importVerification($kyc, 'import_ref', $response);
        $kyc->refresh();

        $this->assertNotNull($kyc->started_at);
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
