<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides standardized JSON API response helpers for controllers and related classes.
 *
 * Methods are structured so Scramble can infer response schemas: HTTP status codes are
 * literals (not dynamic unions), and success payloads are preserved via @template TData.
 */
trait APIResponse
{
    /**
     * Return a successful API response.
     *
     * @template TData
     *
     * @param  TData  $data  Payload to include in the response body.
     * @param  string  $message  Human-readable success message.
     * @param  array<string, mixed>|null  $meta  Optional metadata (pagination, totals, etc.).
     * @return JsonResponse<array{success: true, message: string, data: TData, meta: array<string, mixed>|null, errors: null}, 200>
     */
    protected function success(
        mixed $data = null,
        string $message = 'Request successful.',
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($data),
            'meta' => $meta,
            'errors' => null,
        ], Response::HTTP_OK);
    }

    /**
     * Return a failed API response.
     *
     * @template TErrors of array<string, mixed>|null
     * @template TData
     *
     * @param  string  $message  Human-readable error message.
     * @param  int  $status  HTTP status code.
     * @param  TErrors  $errors  Optional structured error details.
     * @param  TData  $data  Optional payload associated with the failure.
     * @param  array<string, mixed>|null  $meta  Optional metadata.
     * @return JsonResponse<array{success: false, message: string, data: TData, meta: array<string, mixed>|null, errors: TErrors}, int>
     */
    protected function error(
        string $message = 'Something went wrong.',
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
        mixed $data = null,
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a successful API response for a newly created resource.
     *
     * @template TData
     *
     * @param  TData  $data  Created resource payload.
     * @param  string  $message  Human-readable success message.
     * @param  array<string, mixed>|null  $meta  Optional metadata.
     * @return JsonResponse<array{success: true, message: string, data: TData, meta: array<string, mixed>|null, errors: null}, 201>
     */
    protected function created(
        mixed $data = null,
        string $message = 'Resource created successfully.',
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($data),
            'meta' => $meta,
            'errors' => null,
        ], Response::HTTP_CREATED);
    }

    /**
     * Return a successful API response for an updated resource.
     *
     * @template TData
     *
     * @param  TData  $data  Updated resource payload.
     * @param  string  $message  Human-readable success message.
     * @param  array<string, mixed>|null  $meta  Optional metadata.
     * @return JsonResponse<array{success: true, message: string, data: TData, meta: array<string, mixed>|null, errors: null}, 200>
     */
    protected function updated(
        mixed $data = null,
        string $message = 'Resource updated successfully.',
        ?array $meta = null,
    ): JsonResponse {
        return $this->success($data, $message, $meta);
    }

    /**
     * Return a successful API response for a deleted resource.
     *
     * @template TData
     *
     * @param  string  $message  Human-readable success message.
     * @param  TData  $data  Optional payload related to the deletion.
     * @return JsonResponse<array{success: true, message: string, data: TData, meta: null, errors: null}, 200>
     */
    protected function deleted(
        string $message = 'Resource deleted successfully.',
        mixed $data = null,
    ): JsonResponse {
        return $this->success($data, $message);
    }

    /**
     * Return an unauthorized API response.
     *
     * @param  string  $message  Human-readable unauthorized message.
     * @return JsonResponse<array{success: false, message: string, data: null, meta: null, errors: null}, 401>
     */
    protected function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden API response.
     *
     * @param  string  $message  Human-readable forbidden message.
     * @return JsonResponse<array{success: false, message: string, data: null, meta: null, errors: null}, 403>
     */
    protected function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a not found API response.
     *
     * @param  string  $message  Human-readable not found message.
     * @return JsonResponse<array{success: false, message: string, data: null, meta: null, errors: null}, 404>
     */
    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => null,
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a validation error API response.
     *
     * @param  array<string, mixed>  $errors  Validation error bag keyed by field.
     * @param  string  $message  Human-readable validation message.
     * @return JsonResponse<array{success: false, message: string, data: null, meta: null, errors: array<string, mixed>}, 422>
     */
    protected function validationError(
        array $errors,
        string $message = 'The given data was invalid.',
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Return a server error API response.
     *
     * @param  string  $message  Human-readable server error message.
     * @return JsonResponse<array{success: false, message: string, data: null, meta: null, errors: null}, 500>
     */
    protected function serverError(string $message = 'Internal server error.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'errors' => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Build pagination metadata for a length-aware paginator.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Normalize resource payloads so the envelope keeps a flat `data` key.
     */
    protected function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }
}
