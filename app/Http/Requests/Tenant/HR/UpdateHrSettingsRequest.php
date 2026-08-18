<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\PayFrequency;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Update tenant HR settings.
 */
class UpdateHrSettingsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hr\.enabled' => ['sometimes', 'boolean'],
            'hr\.employee_code_prefix' => ['sometimes', 'string', 'max:20'],
            'hr\.default_employment_status' => ['sometimes', 'string', Rule::enum(EmploymentStatus::class)],
            'hr\.attendance\.enabled' => ['sometimes', 'boolean'],
            'hr\.working_days' => ['sometimes', 'string', 'max:32', 'regex:/^[1-7](,[1-7])*$/'],
            'hr\.work_start_time' => ['sometimes', 'date_format:H:i'],
            'hr\.late_tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'hr\.leave\.enabled' => ['sometimes', 'boolean'],
            'hr\.leave\.approval_required' => ['sometimes', 'boolean'],
            'hr\.leave\.max_consecutive_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'hr\.leave\.year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'hr\.payroll\.enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.frequency' => ['sometimes', 'string', Rule::enum(PayFrequency::class)],
            'hr\.payroll\.currency' => ['sometimes', 'string', 'size:3'],
            'hr\.payroll\.approval_required' => ['sometimes', 'boolean'],
            'hr\.payroll\.payment_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'hr\.payroll\.expense_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'hr\.payroll\.payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'hr\.payroll\.tax_payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'hr\.payroll\.deduction_payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'hr\.payroll\.tax_enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.tax_table_id' => ['sometimes', 'nullable', 'integer', 'exists:tax_tables,id'],
            'hr\.payroll\.tax_ytd_enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.tax_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'hr\.payroll\.pension_enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.pension_employee_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'hr\.payroll\.pension_employer_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'hr\.payroll\.nhf_enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.nhf_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'hr\.payroll\.nsitf_enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.nsitf_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'hr\.payroll\.nibss\.enabled' => ['sometimes', 'boolean'],
            'hr\.payroll\.nibss\.base_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'hr\.payroll\.nibss\.api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hr\.payroll\.nibss\.institution_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hr\.payroll\.nibss\.originator_account' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hr\.payroll\.nibss\.originator_bank_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'hr\.overtime\.enabled' => ['sometimes', 'boolean'],
            'hr\.overtime\.rate_percent' => ['sometimes', 'integer', 'min:100', 'max:400'],
            'hr\.overtime\.weekly_enabled' => ['sometimes', 'boolean'],
            'hr\.overtime\.weekly_threshold_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'hr\.overtime\.weekly_rate_percent' => ['sometimes', 'integer', 'min:100', 'max:400'],
            'hr\.working_hours_per_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'hr\.attendance\.gps_required' => ['sometimes', 'boolean'],
            'hr\.attendance\.geofence_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'hr\.attendance\.geofence_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'hr\.attendance\.geofence_radius_meters' => ['sometimes', 'integer', 'min:0', 'max:50000'],
            'hr\.attendance\.biometric_required' => ['sometimes', 'boolean'],
            'hr\.recruitment\.enabled' => ['sometimes', 'boolean'],
            'hr\.performance\.enabled' => ['sometimes', 'boolean'],
            'hr\.leave\.carry_over_enabled' => ['sometimes', 'boolean'],
            'hr\.leave\.carry_over_max_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'hr\.notifications\.leave' => ['sometimes', 'boolean'],
            'hr\.notifications\.payroll' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        return $this->validated();
    }
}
