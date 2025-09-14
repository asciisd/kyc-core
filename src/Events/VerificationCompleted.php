<?php

namespace Asciisd\KycCore\Events;

use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VerificationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly string $reference,
        public readonly KycVerificationResponse $response
    ) {}
}
