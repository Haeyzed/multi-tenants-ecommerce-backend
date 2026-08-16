<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Payment;

use App\Services\Payment\PaymentManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upsert tenant payment gateway settings.
 */
class UpsertPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('tenant')?->can('payments.manage')
            || $this->user('tenant')?->can('payment_gateways.manage')
            || false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $drivers */
        $drivers = app(PaymentManager::class)->drivers();

        return [
            'gateway' => ['required', 'string', Rule::in($drivers)],
            'is_enabled' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable'],
            'settings' => ['sometimes', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
