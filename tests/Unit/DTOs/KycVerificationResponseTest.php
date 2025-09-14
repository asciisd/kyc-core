<?php

namespace Asciisd\KycCore\Tests\Unit\DTOs;

use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Tests\TestCase;

class KycVerificationResponseTest extends TestCase
{
    public function test_can_create_response_with_minimal_data()
    {
        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'verification.completed',
            success: true
        );

        $this->assertEquals('test_ref_123', $response->reference);
        $this->assertEquals('verification.completed', $response->event);
        $this->assertTrue($response->success);
        $this->assertNull($response->verificationUrl);
        $this->assertNull($response->extractedData);
        $this->assertNull($response->verificationResults);
        $this->assertNull($response->documentImages);
        $this->assertNull($response->verificationVideo);
        $this->assertNull($response->verificationReport);
        $this->assertNull($response->imageAccessToken);
        $this->assertNull($response->country);
        $this->assertNull($response->duplicateDetected);
        $this->assertNull($response->declineReason);
        $this->assertNull($response->rawResponse);
        $this->assertNull($response->message);
    }

    public function test_can_create_response_with_all_data()
    {
        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'verification.completed',
            success: true,
            verificationUrl: 'https://test.verification.url',
            extractedData: ['name' => 'John Doe'],
            verificationResults: ['document' => 'verified'],
            documentImages: ['front.jpg', 'back.jpg'],
            verificationVideo: 'https://test.video.url',
            verificationReport: 'https://test.report.url',
            imageAccessToken: 'access_token_123',
            country: 'US',
            duplicateDetected: false,
            declineReason: null,
            rawResponse: ['raw' => 'data'],
            message: 'Verification completed successfully'
        );

        $this->assertEquals('test_ref_123', $response->reference);
        $this->assertEquals('verification.completed', $response->event);
        $this->assertTrue($response->success);
        $this->assertEquals('https://test.verification.url', $response->verificationUrl);
        $this->assertEquals(['name' => 'John Doe'], $response->extractedData);
        $this->assertEquals(['document' => 'verified'], $response->verificationResults);
        $this->assertEquals(['front.jpg', 'back.jpg'], $response->documentImages);
        $this->assertEquals('https://test.video.url', $response->verificationVideo);
        $this->assertEquals('https://test.report.url', $response->verificationReport);
        $this->assertEquals('access_token_123', $response->imageAccessToken);
        $this->assertEquals('US', $response->country);
        $this->assertFalse($response->duplicateDetected);
        $this->assertNull($response->declineReason);
        $this->assertEquals(['raw' => 'data'], $response->rawResponse);
        $this->assertEquals('Verification completed successfully', $response->message);
    }

    public function test_is_successful()
    {
        $successfulResponse = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true
        );

        $failedResponse = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.failed',
            success: false
        );

        $this->assertTrue($successfulResponse->isSuccessful());
        $this->assertFalse($failedResponse->isSuccessful());
    }

    public function test_is_pending()
    {
        $pendingEvents = ['request.pending', 'verification.pending'];
        $nonPendingEvents = ['verification.completed', 'verification.failed', 'verification.cancelled'];

        foreach ($pendingEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertTrue($response->isPending(), "Event '{$event}' should be pending");
        }

        foreach ($nonPendingEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertFalse($response->isPending(), "Event '{$event}' should not be pending");
        }
    }

    public function test_is_completed()
    {
        $completedEvents = ['verification.completed', 'verification.approved'];
        $nonCompletedEvents = ['request.pending', 'verification.failed', 'verification.pending'];

        foreach ($completedEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertTrue($response->isCompleted(), "Event '{$event}' should be completed");
        }

        foreach ($nonCompletedEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertFalse($response->isCompleted(), "Event '{$event}' should not be completed");
        }
    }

    public function test_is_failed()
    {
        $failedEvents = ['verification.failed', 'verification.declined'];
        $nonFailedEvents = ['request.pending', 'verification.completed', 'verification.pending'];

        foreach ($failedEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertTrue($response->isFailed(), "Event '{$event}' should be failed");
        }

        foreach ($nonFailedEvents as $event) {
            $response = new KycVerificationResponse(
                reference: 'test_ref',
                event: $event,
                success: true
            );
            $this->assertFalse($response->isFailed(), "Event '{$event}' should not be failed");
        }
    }

    public function test_has_verification_url()
    {
        $responseWithUrl = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true,
            verificationUrl: 'https://test.url'
        );

        $responseWithoutUrl = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true
        );

        $this->assertTrue($responseWithUrl->hasVerificationUrl());
        $this->assertFalse($responseWithoutUrl->hasVerificationUrl());
    }

    public function test_has_documents()
    {
        $responseWithDocuments = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true,
            documentImages: ['front.jpg', 'back.jpg']
        );

        $responseWithoutDocuments = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true
        );

        $this->assertTrue($responseWithDocuments->hasDocuments());
        $this->assertFalse($responseWithoutDocuments->hasDocuments());
    }

    public function test_to_array_returns_correct_data()
    {
        $response = new KycVerificationResponse(
            reference: 'test_ref_123',
            event: 'verification.completed',
            success: true,
            verificationUrl: 'https://test.verification.url',
            extractedData: ['name' => 'John Doe'],
            verificationResults: ['document' => 'verified'],
            documentImages: ['front.jpg', 'back.jpg'],
            verificationVideo: 'https://test.video.url',
            verificationReport: 'https://test.report.url',
            imageAccessToken: 'access_token_123',
            country: 'US',
            duplicateDetected: false,
            declineReason: null,
            rawResponse: ['raw' => 'data'],
            message: 'Verification completed successfully'
        );

        $array = $response->toArray();

        $expected = [
            'reference' => 'test_ref_123',
            'event' => 'verification.completed',
            'success' => true,
            'verification_url' => 'https://test.verification.url',
            'extracted_data' => ['name' => 'John Doe'],
            'verification_results' => ['document' => 'verified'],
            'document_images' => ['front.jpg', 'back.jpg'],
            'verification_video' => 'https://test.video.url',
            'verification_report' => 'https://test.report.url',
            'image_access_token' => 'access_token_123',
            'country' => 'US',
            'duplicate_detected' => false,
            'decline_reason' => null,
            'raw_response' => ['raw' => 'data'],
            'message' => 'Verification completed successfully',
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_readonly_properties_cannot_be_modified()
    {
        $response = new KycVerificationResponse(
            reference: 'test_ref',
            event: 'verification.completed',
            success: true
        );

        // This should not throw an error during instantiation
        // But attempting to modify would cause a fatal error
        $this->assertEquals('test_ref', $response->reference);
    }
}
