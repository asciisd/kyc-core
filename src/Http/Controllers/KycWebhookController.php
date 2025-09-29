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
            $payload = $request->all();
            $headers = $request->headers->all();
            
            Log::info('KYC Webhook received', [
                'payload' => $payload,
                'headers' => $headers,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Validate required fields
            $reference = $payload['reference'] ?? null;
            $event = $payload['event'] ?? null;

            if (!$reference) {
                Log::warning('KYC webhook missing reference', ['payload' => $payload]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing reference parameter',
                ], 400);
            }

            $result = $this->kycManager->processWebhook($payload, $headers);

            Log::info('KYC Webhook processed successfully', [
                'reference' => $result->reference,
                'event' => $result->event ?? $event,
                'success' => $result->success,
                'webhook_type' => $event === 'request.data.changed' ? 'data_merge' : 'standard',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'reference' => $result->reference,
                'event' => $event,
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('KYC Webhook validation failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
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
    public function complete(Request $request)
    {
        try {
            $reference = $request->query('reference');
            $status = $request->query('status', 'completed');

            Log::info('KYC Verification completion callback', [
                'reference' => $reference,
                'status' => $status,
                'query_params' => $request->query(),
            ]);

            if (! $reference) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reference parameter is required',
                    ], 400);
                }

                // Redirect to KYC page with error for non-JSON requests
                return redirect()->route('kyc.index')->withErrors([
                    'kyc' => 'Verification reference is missing. Please try again.',
                ]);
            }

            // Find the KYC record
            $kyc = Kyc::where('reference', $reference)->first();

            if (! $kyc) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'KYC record not found',
                    ], 404);
                }

                // Redirect to KYC page with error for non-JSON requests
                return redirect()->route('kyc.index')->withErrors([
                    'kyc' => 'Verification record not found. Please contact support.',
                ]);
            }

            // Get the verification status from the provider and update the record
            $result = $this->kycManager->retrieveVerification($reference);

            // Get the driver to properly map the event to status and update the KYC record
            $driver = $this->kycManager->driver($kyc->driver);
            $mappedStatus = $driver->mapEventToStatus($result->event);

            // Store the complete response data directly with completion metadata
            $data = array_merge($result->toArray(), [
                'last_completion_at' => now()->toISOString(),
                'completion_status' => $status,
            ]);

            // Update the KYC record with the complete data
            $kyc->updateKycStatus($mappedStatus, $data);

            Log::info('KYC Verification completion processed', [
                'reference' => $reference,
                'previous_status' => $kyc->getOriginal('status'),
                'new_status' => $mappedStatus->value,
                'provider_event' => $result->event,
                'response_keys' => array_keys($result->toArray()),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification completion processed',
                    'reference' => $reference,
                    'status' => $mappedStatus->value,
                    'data' => $data,
                ]);
            }

            // For non-JSON requests, redirect to the application's KYC callback page
            return redirect()->route('kyc.index')->with([
                'success' => 'Verification completed successfully!',
                'kyc_status' => $mappedStatus->value,
                'reference' => $reference,
            ]);

        } catch (\Exception $e) {
            Log::error('KYC Verification completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $request->query('reference'),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification completion processing failed',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            // Redirect to KYC page with error for non-JSON requests
            return redirect()->route('kyc.index')->withErrors([
                'kyc' => 'Verification processing failed. Please contact support if the issue persists.',
            ]);
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
                    ->filter(fn ($config) => $config['enabled'] ?? false)
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
