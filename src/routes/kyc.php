<?php

use Asciisd\KycCore\Http\Controllers\KycWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KYC Infrastructure Routes
|--------------------------------------------------------------------------
|
| These routes handle the core KYC infrastructure functionality that is
| provider-agnostic and should be consistent across all applications.
| These routes are automatically registered by the KycServiceProvider.
|
*/

// Webhook routes (no authentication required)
Route::post('/api/kyc/webhook', [KycWebhookController::class, 'webhook'])
    ->name('kyc.webhook')
    ->middleware(['api']);

Route::post('/api/kyc/webhook/callback', [KycWebhookController::class, 'webhook'])
    ->name('kyc.webhook.callback')
    ->middleware(['api']);

// Verification completion callback (no authentication required)
Route::get('/api/kyc/verification/complete', [KycWebhookController::class, 'complete'])
    ->name('kyc.verification.complete')
    ->middleware(['api']);

// Health check endpoint (no authentication required)
Route::get('/api/kyc/health', [KycWebhookController::class, 'health'])
    ->name('kyc.health')
    ->middleware(['api']);

// Also register non-API versions for backward compatibility
Route::post('/kyc/webhook', [KycWebhookController::class, 'webhook'])
    ->middleware(['api']);

Route::post('/kyc/webhook/callback', [KycWebhookController::class, 'webhook'])
    ->middleware(['api']);

Route::get('/kyc/verification/complete', [KycWebhookController::class, 'complete'])
    ->middleware(['api']);

Route::get('/kyc/health', [KycWebhookController::class, 'health'])
    ->middleware(['api']);
