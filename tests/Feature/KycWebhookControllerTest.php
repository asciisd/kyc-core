<?php

namespace Asciisd\KycCore\Tests\Feature;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class KycWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected Kyc $kycRecord;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = $this->createTestUser();
        
        // Create a KYC record
        $this->kycRecord = Kyc::create([
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'kycable_type' => get_class($this->user),
            'kycable_id' => $this->user->id,
            'driver' => 'shuftipro',
            'status' => KycStatusEnum::InProgress,
            'data' => [
                'verification_data' => [
                    'document' => [
                        'name' => [
                            'full_name' => 'John Doe',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                        ],
                    ],
                ],
                'existing_field' => 'should_be_preserved',
            ],
        ]);
    }

    /** @test */
    public function it_handles_request_data_changed_webhook_successfully()
    {
        // Mock the driver
        $this->mockKycDriver([
            'processWebhook' => $this->createMockWebhookResponse([
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'request.data.changed',
                'success' => true,
            ]),
        ]);

        $webhookPayload = [
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'event' => 'request.data.changed',
            'verification_data' => [
                'document' => [
                    'name' => [
                        'full_name' => 'AMR EMAD ELDIN AHMED ABDELKADIR',
                        'first_name' => 'AMR',
                        'last_name' => 'ABDELKADIR',
                        'middle_name' => 'EMAD ELDIN AHMED',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC data updated successfully via request.data.changed', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC Webhook processed successfully', \Mockery::type('array'))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'request.data.changed',
            ]);

        // Verify data was merged correctly
        $this->kycRecord->refresh();
        $kycData = $this->kycRecord->data;

        $this->assertEquals('AMR EMAD ELDIN AHMED ABDELKADIR', $kycData['verification_data']['document']['name']['full_name']);
        $this->assertEquals('AMR', $kycData['verification_data']['document']['name']['first_name']);
        $this->assertEquals('ABDELKADIR', $kycData['verification_data']['document']['name']['last_name']);
        $this->assertEquals('EMAD ELDIN AHMED', $kycData['verification_data']['document']['name']['middle_name']);
        $this->assertEquals('should_be_preserved', $kycData['existing_field']);
    }

    /** @test */
    public function it_handles_standard_webhook_events()
    {
        // Mock the driver and status service
        $this->mockKycDriver([
            'processWebhook' => $this->createMockWebhookResponse([
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'verification.accepted',
                'success' => true,
            ]),
        ]);

        $webhookPayload = [
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'event' => 'verification.accepted',
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC Webhook processed successfully', \Mockery::type('array'))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'verification.accepted',
            ]);
    }

    /** @test */
    public function it_returns_400_when_reference_is_missing()
    {
        $webhookPayload = [
            'event' => 'request.data.changed',
            'verification_data' => [
                'document' => [
                    'name' => [
                        'full_name' => 'Test User',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('warning')
            ->with('KYC webhook missing reference', \Mockery::type('array'))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Missing reference parameter',
            ]);
    }

    /** @test */
    public function it_returns_404_when_kyc_record_not_found()
    {
        // Mock the driver
        $this->mockKycDriver([
            'processWebhook' => $this->createMockWebhookResponse([
                'reference' => 'NONEXISTENT_REFERENCE',
                'event' => 'request.data.changed',
                'success' => true,
            ]),
        ]);

        $webhookPayload = [
            'reference' => 'NONEXISTENT_REFERENCE',
            'event' => 'request.data.changed',
            'verification_data' => [
                'document' => [
                    'name' => [
                        'full_name' => 'Test User',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('warning')
            ->with('KYC Webhook validation failed', \Mockery::type('array'))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'KYC record not found for reference: NONEXISTENT_REFERENCE',
            ]);
    }

    /** @test */
    public function it_handles_webhook_processing_exceptions()
    {
        // Mock the driver to throw an exception
        $this->mockKycDriver([
            'processWebhook' => function () {
                throw new \Exception('Driver processing failed');
            },
        ]);

        $webhookPayload = [
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'event' => 'request.data.changed',
            'verification_data' => [
                'document' => [
                    'name' => [
                        'full_name' => 'Test User',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('error')
            ->with('KYC Webhook processing failed', \Mockery::type('array'))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Webhook processing failed',
            ]);
    }

    /** @test */
    public function it_logs_webhook_type_correctly()
    {
        // Mock the driver
        $this->mockKycDriver([
            'processWebhook' => $this->createMockWebhookResponse([
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'request.data.changed',
                'success' => true,
            ]),
        ]);

        $webhookPayload = [
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'event' => 'request.data.changed',
            'verification_data' => [
                'document' => [
                    'name' => [
                        'full_name' => 'Test User',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC data updated successfully via request.data.changed', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC Webhook processed successfully', \Mockery::on(function ($data) {
                return $data['webhook_type'] === 'data_merge' && 
                       $data['event'] === 'request.data.changed' &&
                       $data['reference'] === 'SP_1759106976_68d9d7a0a0f8a';
            }))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_logs_standard_webhook_type_correctly()
    {
        // Mock the driver
        $this->mockKycDriver([
            'processWebhook' => $this->createMockWebhookResponse([
                'reference' => 'SP_1759106976_68d9d7a0a0f8a',
                'event' => 'verification.accepted',
                'success' => true,
            ]),
        ]);

        $webhookPayload = [
            'reference' => 'SP_1759106976_68d9d7a0a0f8a',
            'event' => 'verification.accepted',
        ];

        Log::shouldReceive('info')
            ->with('KYC Webhook received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('KYC Webhook processed successfully', \Mockery::on(function ($data) {
                return $data['webhook_type'] === 'standard' && 
                       $data['event'] === 'verification.accepted' &&
                       $data['reference'] === 'SP_1759106976_68d9d7a0a0f8a';
            }))
            ->once();

        $response = $this->postJson('/api/kyc/webhook', $webhookPayload);

        $response->assertStatus(200);
    }
}
