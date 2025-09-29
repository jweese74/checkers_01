<?php

namespace App\Domain;

class MoveResult
{
    public function __construct(
        public array $state,
        public ?string $message,
        public ?array $lastMove,
        public array $captured,
        public string $status
    ) {
    }
}
