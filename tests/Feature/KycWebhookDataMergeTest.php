<?php

namespace Asciisd\KycCore\Tests\Feature;

use Asciisd\KycCore\Enums\KycStatusEnum;
use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\KycManager;
use Asciisd\KycCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class KycWebhookDataMergeTest extends TestCase
{
    use RefreshDatabase;

    protected KycManager $kycManager;
    protected $user;
    protected Kyc $kycRecord;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->kycManager = app(KycManager::class);
        
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
                        'number' => 'DOC123456',
                        'type' => 'passport',
                    ],
                    'address' => [
                        'country' => 'US',
                        'city' => 'New York',
                    ],
                ],
                'existing_field' => 'should_be_preserved',
                'nested_data' => [
                    'existing_nested' => 'preserved_value',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_handles_request_data_changed_webhook_with_deep_merge()
    {
        // Mock the driver to return a proper response
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

        // Process the webhook
        $result = $this->kycManager->processWebhook($webhookPayload);

        // Assert the response
        $this->assertEquals('SP_1759106976_68d9d7a0a0f8a', $result->reference);
        $this->assertTrue($result->success);

        // Refresh the KYC record and check merged data
        $this->kycRecord->refresh();
        $kycData = $this->kycRecord->data;

        // Check that new data was merged
        $this->assertEquals('AMR EMAD ELDIN AHMED ABDELKADIR', $kycData['verification_data']['document']['name']['full_name']);
        $this->assertEquals('AMR', $kycData['verification_data']['document']['name']['first_name']);
        $this->assertEquals('ABDELKADIR', $kycData['verification_data']['document']['name']['last_name']);
        $this->assertEquals('EMAD ELDIN AHMED', $kycData['verification_data']['document']['name']['middle_name']);

        // Check that existing data was preserved
        $this->assertEquals('DOC123456', $kycData['verification_data']['document']['number']);
        $this->assertEquals('passport', $kycData['verification_data']['document']['type']);
        $this->assertEquals('US', $kycData['verification_data']['address']['country']);
        $this->assertEquals('New York', $kycData['verification_data']['address']['city']);
        $this->assertEquals('should_be_preserved', $kycData['existing_field']);
        $this->assertEquals('preserved_value', $kycData['nested_data']['existing_nested']);

        // Check that webhook metadata was added
        $this->assertEquals('request.data.changed', $kycData['last_webhook_event']);
        $this->assertArrayHasKey('last_webhook_at', $kycData);
        $this->assertArrayHasKey('data_updated_at', $kycData);
    }

    /** @test */
    public function it_preserves_existing_nested_data_when_merging()
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
                        'full_name' => 'Updated Name',
                        'middle_name' => 'New Middle',
                    ],
                    'new_field' => 'new_value',
                ],
                'new_section' => [
                    'new_data' => 'test',
                ],
            ],
        ];

        $this->kycManager->processWebhook($webhookPayload);

        $this->kycRecord->refresh();
        $kycData = $this->kycRecord->data;

        // Check updated fields
        $this->assertEquals('Updated Name', $kycData['verification_data']['document']['name']['full_name']);
        $this->assertEquals('New Middle', $kycData['verification_data']['document']['name']['middle_name']);
        $this->assertEquals('new_value', $kycData['verification_data']['document']['new_field']);
        $this->assertEquals('test', $kycData['verification_data']['new_section']['new_data']);

        // Check preserved fields
        $this->assertEquals('John', $kycData['verification_data']['document']['name']['first_name']);
        $this->assertEquals('Doe', $kycData['verification_data']['document']['name']['last_name']);
        $this->assertEquals('DOC123456', $kycData['verification_data']['document']['number']);
        $this->assertEquals('passport', $kycData['verification_data']['document']['type']);
        $this->assertEquals('US', $kycData['verification_data']['address']['country']);
        $this->assertEquals('New York', $kycData['verification_data']['address']['city']);
    }

    /** @test */
    public function it_handles_empty_verification_data_gracefully()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('No verification_data in request.data.changed event', \Mockery::type('array'));

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
            // No verification_data
        ];

        $originalData = $this->kycRecord->data;

        $this->kycManager->processWebhook($webhookPayload);

        $this->kycRecord->refresh();
        
        // Data should remain unchanged
        $this->assertEquals($originalData, $this->kycRecord->data);
    }

    /** @test */
    public function it_handles_standard_webhook_events_normally()
    {
        // Mock the driver and status service for standard processing
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

        $result = $this->kycManager->processWebhook($webhookPayload);

        $this->assertEquals('SP_1759106976_68d9d7a0a0f8a', $result->reference);
        $this->assertTrue($result->success);
        
        // Should not have data merge metadata for standard events
        $this->kycRecord->refresh();
        $kycData = $this->kycRecord->data;
        $this->assertArrayNotHasKey('data_updated_at', $kycData);
    }

    /** @test */
    public function it_throws_exception_for_missing_kyc_record()
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('KYC record not found for reference: NONEXISTENT_REFERENCE');

        $this->kycManager->processWebhook($webhookPayload);
    }

    /** @test */
    public function it_triggers_user_data_population_for_completed_kyc()
    {
        // Set KYC status to completed
        $this->kycRecord->update(['status' => KycStatusEnum::VerificationCompleted]);

        // Mock user with populateFromKyc method
        $userMock = \Mockery::mock($this->user)->makePartial();
        $userMock->shouldReceive('populateFromKyc')->once();
        
        $this->kycRecord->kycable = $userMock;

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
                        'full_name' => 'Updated Name',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC data updated successfully via request.data.changed', \Mockery::type('array'));

        $this->kycManager->processWebhook($webhookPayload);
    }

    /** @test */
    public function it_does_not_trigger_user_population_for_incomplete_kyc()
    {
        // Keep KYC status as InProgress
        $this->assertEquals(KycStatusEnum::InProgress, $this->kycRecord->status);

        // Mock user - should NOT call populateFromKyc
        $userMock = \Mockery::mock($this->user)->makePartial();
        $userMock->shouldNotReceive('populateFromKyc');
        
        $this->kycRecord->kycable = $userMock;

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
                        'full_name' => 'Updated Name',
                    ],
                ],
            ],
        ];

        Log::shouldReceive('info')
            ->with('KYC data updated successfully via request.data.changed', \Mockery::type('array'));

        $this->kycManager->processWebhook($webhookPayload);
    }

    /** @test */
    public function deep_merge_handles_complex_nested_structures()
    {
        // Test the deepMergeKycData method directly through reflection
        $reflection = new \ReflectionClass($this->kycManager);
        $method = $reflection->getMethod('deepMergeKycData');
        $method->setAccessible(true);

        $existing = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'existing_field' => 'keep_this',
                        'update_field' => 'old_value',
                    ],
                    'keep_this_array' => ['item1', 'item2'],
                ],
                'keep_this_field' => 'preserved',
            ],
            'root_field' => 'root_value',
        ];

        $new = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'update_field' => 'new_value',
                        'new_field' => 'added_value',
                    ],
                    'new_array' => ['new_item'],
                ],
                'new_field' => 'added_at_level1',
            ],
            'new_root_field' => 'new_root_value',
        ];

        $result = $method->invoke($this->kycManager, $existing, $new);

        // Check preserved data
        $this->assertEquals('keep_this', $result['level1']['level2']['level3']['existing_field']);
        $this->assertEquals(['item1', 'item2'], $result['level1']['level2']['keep_this_array']);
        $this->assertEquals('preserved', $result['level1']['keep_this_field']);
        $this->assertEquals('root_value', $result['root_field']);

        // Check updated data
        $this->assertEquals('new_value', $result['level1']['level2']['level3']['update_field']);
        $this->assertEquals('added_value', $result['level1']['level2']['level3']['new_field']);
        $this->assertEquals(['new_item'], $result['level1']['level2']['new_array']);
        $this->assertEquals('added_at_level1', $result['level1']['new_field']);
        $this->assertEquals('new_root_value', $result['new_root_field']);
    }
}
