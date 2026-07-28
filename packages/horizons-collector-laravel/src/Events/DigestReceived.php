<?php

namespace HorizonsPlus\CollectorLaravel\Events;

class DigestReceived
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}
}
