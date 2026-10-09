<?php

namespace App\Jobs;

use App\Actions\ProcessAiReviewAction;
use App\Models\AiReview;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Throwable;

class ProcessAiReview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 4;

    public int $timeout = 270;

    public int $uniqueFor = 3600;

    public bool $failOnTimeout = true;

    /** @param array<string, mixed> $sources */
    public function __construct(
        public readonly string $reviewId,
        public readonly array $sources,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ProcessAiReviewAction $process): void
    {
        try {
            $process->handle($this->reviewId, $this->sources);
        } catch (Throwable $exception) {
            if ($this->isRetryable($exception)) {
                $retryAfter = $this->retryAfter($exception);

                if ($retryAfter !== null) {
                    $this->release($retryAfter);

                    return;
                }

                throw $exception;
            }

            $process->markFailed($this->reviewId, $exception, max(0, $this->attempts() - 1));
            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ProcessAiReviewAction::class)->markFailed(
            $this->reviewId,
            $exception ?? new RuntimeException('AI review job failed without exception context.'),
            max(0, min($this->attempts() - 1, $this->maxExceptions - 1)),
        );
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function backoff(): int
    {
        $baseDelay = match (min(max($this->attempts(), 1), 3)) {
            1 => 15,
            2 => 60,
            default => 180,
        };

        return min($baseDelay + random_int(0, max(1, (int) floor($baseDelay * 0.2))), 300);
    }

    public function uniqueId(): string
    {
        return $this->reviewId;
    }

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        $review = AiReview::query()
            ->with('activity:id,unit_id')
            ->find($this->reviewId);

        if ($review === null) {
            return [];
        }

        return [
            (new WithoutOverlapping('ai-user-'.$review->requested_by))->releaseAfter(15)->expireAfter(300)->shared(),
            (new WithoutOverlapping('ai-unit-'.$review->activity->unit_id))->releaseAfter(15)->expireAfter(300)->shared(),
        ];
    }

    private function isRetryable(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException
                && ($exception->response->status() === 429 || $exception->response->serverError()));
    }

    private function retryAfter(Throwable $exception): ?int
    {
        if (! $exception instanceof RequestException) {
            return null;
        }

        $header = trim((string) $exception->response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return min(max((int) $header, 1), 300);
        }

        try {
            $retryAt = new DateTimeImmutable($header);
        } catch (Throwable) {
            return null;
        }

        return min(max($retryAt->getTimestamp() - now()->getTimestamp(), 1), 300);
    }
}
