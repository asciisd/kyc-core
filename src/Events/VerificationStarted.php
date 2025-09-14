<?php

namespace Asciisd\KycCore\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VerificationStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly string $reference,
        public readonly string $driver
    ) {}
}
