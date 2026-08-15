<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payment\Webhooks\WebhookProcessor;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin payment provider webhook receiver.
 */
class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly WebhookProcessor $webhookProcessor) {}

    /**
     * Handle an inbound payment provider webhook.
     */
    #[Response(
        status: 200,
        description: 'Webhook accepted.',
        type: 'array{success: true, message: string, data: array{processed: bool, duplicate?: bool, event_type?: string|null}, meta: null, errors: null}',
    )]
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $result = $this->webhookProcessor->process($provider, $request);

        return $this->success($result, 'Webhook processed.');
    }
}
