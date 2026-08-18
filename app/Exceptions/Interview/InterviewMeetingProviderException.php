<?php

declare(strict_types=1);

namespace App\Exceptions\Interview;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * User-facing meeting provider failure. Technical details stay in logs, not the API.
 */
class InterviewMeetingProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Unable to complete the interview meeting request. Please verify the configured meeting provider.',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data' => null,
            'meta' => null,
            'errors' => [
                'meeting' => [$this->getMessage()],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
