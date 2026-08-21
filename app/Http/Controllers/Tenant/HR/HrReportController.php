<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexHrReportRequest;
use App\Services\Tenant\HR\HrCsvExporter;
use App\Services\Tenant\HR\HrReportService;
use App\Services\Tenant\HR\StatutoryReturnService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Detailed HR operational reports.
 */
#[Group('HR')]
class HrReportController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  HrReportService  $reports
     * @param  HrCsvExporter  $csv
     * @param  StatutoryReturnService  $statutory
     */
    public function __construct(
        private readonly HrReportService $reports,
        private readonly HrCsvExporter $csv,
        private readonly StatutoryReturnService $statutory,
    ) {}

    /**
     * Attendance.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Attendance report.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function attendance(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->attendance($request->validated());

        return $this->respond($request, $payload, 'attendance-report.csv', 'Attendance report retrieved successfully.');
    }

    /**
     * Leave.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Leave report.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function leave(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->leave($request->validated());

        return $this->respond($request, $payload, 'leave-report.csv', 'Leave report retrieved successfully.');
    }

    /**
     * Payroll.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Payroll register.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function payroll(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->payroll($request->validated());

        return $this->respond($request, $payload, 'payroll-report.csv', 'Payroll report retrieved successfully.');
    }

    /**
     * Overtime.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Overtime report.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function overtime(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->overtime($request->validated());

        return $this->respond($request, $payload, 'overtime-report.csv', 'Overtime report retrieved successfully.');
    }

    /**
     * Headcount.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Headcount report.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function headcount(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->headcount($request->validated());

        return $this->respond($request, $payload, 'headcount-report.csv', 'Headcount report retrieved successfully.');
    }

    /**
     * Statutory.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Statutory filing schedule.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function statutory(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->statutory->generateOrFail($request->validated());

        return $this->respond($request, $payload, 'statutory-return.csv', 'Statutory return retrieved successfully.');
    }

    /**
     * Recruitment.
     *
     * @param  IndexHrReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(status: 200, description: 'Recruitment report.', type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}')]
    public function recruitment(IndexHrReportRequest $request): JsonResponse|StreamedResponse
    {
        $this->authorize('viewHrReports');

        $payload = $this->reports->recruitment($request->validated());

        return $this->respond($request, $payload, 'recruitment-report.csv', 'Recruitment report retrieved successfully.');
    }

    /**
     * Respond.
     *
     * @param  IndexHrReportRequest  $request
     * @param  array<string, mixed>  $payload
     * @param  string  $filename
     * @param  string  $message
     * @return JsonResponse|StreamedResponse
     */
    protected function respond(IndexHrReportRequest $request, array $payload, string $filename, string $message): JsonResponse|StreamedResponse
    {
        if ($request->wantsCsv()) {
            $rows = $payload['rows'] ?? $payload['by_department'] ?? $payload['by_type'] ?? [];
            $flat = array_map(function (array $row): array {
                foreach ($row as $key => $value) {
                    if ($value instanceof \BackedEnum) {
                        $row[$key] = $value->value;
                    }
                }

                return $row;
            }, $rows);

            return $this->csv->rows($filename, $flat);
        }

        return $this->success($payload, $message);
    }
}
