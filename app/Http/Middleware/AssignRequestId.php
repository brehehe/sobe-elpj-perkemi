<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public const ATTRIBUTE = 'request_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::resolve($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        $request->headers->set('X-Request-ID', $requestId);
        Context::add(self::ATTRIBUTE, $requestId);

        return self::decorate($next($request), $requestId, $request->is('api/*'));
    }

    public static function resolve(Request $request): string
    {
        $assignedRequestId = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($assignedRequestId) && self::isValid($assignedRequestId)) {
            return $assignedRequestId;
        }

        $incomingRequestId = $request->header('X-Request-ID');

        if (is_string($incomingRequestId) && self::isValid($incomingRequestId)) {
            return $incomingRequestId;
        }

        return (string) Str::uuid7();
    }

    public static function decorate(Response $response, string $requestId, bool $usesApiContract): Response
    {
        $response->headers->set('X-Request-ID', $requestId);

        if (! $usesApiContract || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $normalizedPayload = is_array($payload) ? $payload : ['data' => $payload];

        if ($response->getStatusCode() >= 400) {
            $normalizedPayload = self::normalizeErrorPayload(
                $normalizedPayload,
                $response->getStatusCode(),
                $requestId,
            );
        } else {
            $normalizedPayload['request_id'] = $requestId;
        }

        $response->setData($normalizedPayload);

        return $response;
    }

    private static function isValid(string $requestId): bool
    {
        return preg_match('/\A[A-Za-z0-9._:-]{1,100}\z/', $requestId) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, code: string, errors: array<mixed>|stdClass, request_id: string}
     */
    private static function normalizeErrorPayload(array $payload, int $status, string $requestId): array
    {
        $providedMessage = $payload['message'] ?? null;
        $providedCode = $payload['code'] ?? null;
        $providedErrors = $payload['errors'] ?? null;

        return [
            'message' => self::errorMessage($status, $providedMessage),
            'code' => is_string($providedCode) && $providedCode !== ''
                ? $providedCode
                : self::errorCode($status),
            'errors' => is_array($providedErrors) ? $providedErrors : new stdClass,
            'request_id' => $requestId,
        ];
    }

    private static function errorMessage(int $status, mixed $providedMessage): string
    {
        if ($status >= 500) {
            return 'Layanan mengalami gangguan. Gunakan Request ID saat menghubungi dukungan.';
        }

        if ($status === 422) {
            return 'Validasi gagal.';
        }

        if (is_string($providedMessage) && $providedMessage !== '') {
            return $providedMessage;
        }

        return match ($status) {
            400 => 'Permintaan tidak dapat diproses.',
            401 => 'Autentikasi diperlukan untuk mengakses layanan ini.',
            403 => 'Anda tidak memiliki kewenangan untuk tindakan ini.',
            404 => 'Sumber daya yang diminta tidak ditemukan.',
            409 => 'Permintaan bertentangan dengan kondisi data saat ini.',
            419 => 'Sesi permintaan telah berakhir.',
            429 => 'Terlalu banyak permintaan. Coba kembali beberapa saat lagi.',
            default => 'Permintaan tidak dapat diselesaikan.',
        };
    }

    private static function errorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            419 => 'PAGE_EXPIRED',
            422 => 'VALIDATION_FAILED',
            429 => 'RATE_LIMITED',
            503 => 'SERVICE_UNAVAILABLE',
            default => $status >= 500 ? 'INTERNAL_ERROR' : 'HTTP_ERROR',
        };
    }
}
