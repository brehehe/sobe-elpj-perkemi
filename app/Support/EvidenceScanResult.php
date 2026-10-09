<?php

namespace App\Support;

final readonly class EvidenceScanResult
{
    public function __construct(
        public bool $clean,
        public string $detectedMimeType,
        public string $engine,
        public ?string $threat = null,
        public ?string $notes = null,
    ) {}
}
