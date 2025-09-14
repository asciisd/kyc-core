<?php

namespace Asciisd\KycCore\Tests\Unit\Services;

use Asciisd\KycCore\DTOs\KycVerificationRequest;
use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Services\KycManager;
use Asciisd\KycCore\Services\StatusService;
use Asciisd\KycCore\Services\ValidationService;
use Asciisd\KycCore\Tests\TestCase;
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

        $this->assertInstanceOf(\Asciisd\KycCore\Contracts\KycDriverInterface::class, $driver);
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

        Event::assertDispatched(\Asciisd\KycCore\Events\VerificationStarted::class);
    }

    public function test_create_simple_verification()
    {
        Event::fake();

        $user = $this->createTestUser();
        $options = ['country' => 'US'];

        $response = $this->kycManager->createSimpleVerification($user, $options);

        $this->assertInstanceOf(KycVerificationResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        Event::assertDispatched(\Asciisd\KycCore\Events\VerificationStarted::class);
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
