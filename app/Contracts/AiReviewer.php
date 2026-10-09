<?php

namespace App\Contracts;

interface AiReviewer
{
    /**
     * @param  array<string, mixed>  $sources
     * @return array{model: string, output: array<string, mixed>, response_id: string|null, usage: array<string, mixed>, retry_count: int, confidence: float, limitations: string}
     */
    public function review(array $sources, string $type, string $idempotencyKey): array;
}
