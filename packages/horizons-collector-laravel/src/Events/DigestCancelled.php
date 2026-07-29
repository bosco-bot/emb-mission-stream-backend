<?php

namespace HorizonsPlus\CollectorLaravel\Events;

class DigestCancelled
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}
}
