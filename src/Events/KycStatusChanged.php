<?php

namespace Asciisd\KycCore\Events;

use Asciisd\KycCore\DTOs\KycVerificationResponse;
use Asciisd\KycCore\Enums\KycStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly string $reference,
        public readonly KycStatusEnum $previousStatus,
        public readonly KycStatusEnum $newStatus,
        public readonly KycVerificationResponse $response
    ) {}
}
