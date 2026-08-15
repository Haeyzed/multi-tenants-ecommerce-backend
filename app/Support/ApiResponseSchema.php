<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared Scramble/OpenAPI response type strings for the API envelope.
 *
 * Attribute arguments must be constant expressions, so these are class constants.
 */
final class ApiResponseSchema
{
    public const OPTIONS = 'array{success: true, message: string, data: array{label: string, value: int}[], meta: null, errors: null}';

    public const PAGINATION_META = 'array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}';
}
