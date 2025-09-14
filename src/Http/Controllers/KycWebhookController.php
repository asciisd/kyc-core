<?php

namespace Asciisd\KycCore\Http\Controllers;

use Asciisd\KycCore\Models\Kyc;
use Asciisd\KycCore\Services\KycManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KycWebhookController
{
    public function __construct(
        private readonly KycManager $kycManager
    ) {}

    /**
     * Handle KYC webhook callbacks from providers
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            Log::info('KYC Webhook received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $result = $this->kycManager->processWebhook(
                $request->all(),
                $request->headers->all()
            );

            Log::info('KYC Webhook processed successfully', [
                'reference' => $result->reference,
                'event' => $result->event,
                'success' => $result->success,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'reference' => $result->reference,
            ]);
        } catch (\Exception $e) {
            Log::error('KYC Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle KYC verification completion callbacks
     */
    public function complete(Request $request): JsonResponse
    {
        try {
            $reference = $request->query('reference');
            $status = $request->query('status', 'completed');

            Log::info('KYC Verification completion callback', [
                'reference' => $reference,
                'status' => $status,
                'query_params' => $request->query(),
            ]);

            if (!$reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reference parameter is required',
                ], 400);
            }

            // Find the KYC record
            $kyc = Kyc::where('reference', $reference)->first();
            
            if (!$kyc) {
                return response()->json([
                    'success' => false,
                    'message' => 'KYC record not found',
                ], 404);
            }

            // Get the verification status from the provider
            $result = $this->kycManager->retrieveVerification($reference);

            Log::info('KYC Verification completion processed', [
                'reference' => $reference,
                'current_status' => $kyc->status->value,
                'provider_status' => $result->event,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification completion processed',
                'reference' => $reference,
                'status' => $kyc->status->value,
            ]);
        } catch (\Exception $e) {
            Log::error('KYC Verification completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $request->query('reference'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification completion processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Health check endpoint for KYC infrastructure
     */
    public function health(): JsonResponse
    {
        try {
            $drivers = $this->kycManager->getAvailableDrivers();
            $defaultDriver = $this->kycManager->getDefaultDriver();
            
            return response()->json([
                'success' => true,
                'message' => 'KYC infrastructure is healthy',
                'default_driver' => $defaultDriver,
                'available_drivers' => array_keys($drivers),
                'enabled_drivers' => collect($drivers)
                    ->filter(fn($config) => $config['enabled'] ?? false)
                    ->keys()
                    ->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'KYC infrastructure health check failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Service unavailable',
            ], 503);
        }
    }
}
