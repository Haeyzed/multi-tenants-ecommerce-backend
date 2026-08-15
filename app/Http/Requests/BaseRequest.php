<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base Form Request that standardizes API validation failure responses.
 *
 * All application Form Requests should extend this class so validation errors
 * are returned using the shared API response contract.
 */
class BaseRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     *
     * Subclasses should override this method when authorization is required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt by throwing a ValidationException
     * that renders the application's standardized API error envelope.
     *
     * The exception is processed by Laravel's exception handling pipeline and
     * returns HTTP 422 Unprocessable Entity with field-level error details.
     *
     * @param  Validator  $validator  The validator instance containing failed rules.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'The given data was invalid.',
            'data' => null,
            'meta' => null,
            'errors' => $validator->errors()->toArray(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

        throw new ValidationException($validator, $response);
    }
}
